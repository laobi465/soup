<?php
declare (strict_types = 1);

namespace app\service;

use app\library\AesEncrypt;
use app\model\ApkInjectTask;
use app\model\App;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Queue;

class ApkInjectService
{
    private const MAX_CONCURRENT = 3; // 单商户最大并发
    private const DEDUP_WINDOW = 86400; // 24小时去重窗口
    private const CONCURRENT_KEY = 'apk_inject:concurrent:';
    private const QUEUE_NAME = 'apk-inject';

    /**
     * 创建注入任务
     */
    public function createTask(int $merchantId, int $appId, string $filename, int $fileSize, string $sha256): array
    {
        // 1. 校验应用归属
        $app = App::where('id', $appId)->where('merchant_id', $merchantId)->find();
        if (!$app) {
            throw new \RuntimeException('应用不存在或无权限');
        }

        // 2. 并发限制校验（使用 Redis）
        $redis = Cache::store('redis')->handler();
        $concurrentKey = self::CONCURRENT_KEY . $merchantId;
        $current = (int) $redis->get($concurrentKey);
        if ($current >= self::MAX_CONCURRENT) {
            throw new \RuntimeException('并发任务数超限，请等待已有任务完成');
        }

        // 3. SHA-256 去重（24小时内）
        $existing = ApkInjectTask::where('file_sha256', $sha256)
            ->where('merchant_id', $merchantId)
            ->where('created_at', '>', date('Y-m-d H:i:s', time() - self::DEDUP_WINDOW))
            ->whereIn('status', [ApkInjectTask::STATUS_PENDING, ApkInjectTask::STATUS_PROCESSING, ApkInjectTask::STATUS_COMPLETED])
            ->find();
        if ($existing) {
            throw new \RuntimeException('该APK在24小时内已提交过注入任务');
        }

        // 4. 生成任务编号和存储路径
        $taskNo = 'APK' . date('YmdHis') . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $sourcePath = sprintf('apk-source/%s/%s.apk', date('Ymd'), $taskNo);

        // 5. 生成 presigned 上传 URL
        $uploadUrl = StorageService::getApkPresignedUploadUrl($sourcePath, 300);

        // 6. 解密 app_secret 并生成 SDK 配置
        $plainSecret = '';
        if (!empty($app->app_secret_encrypted)) {
            $decrypted = AesEncrypt::decrypt($app->app_secret_encrypted);
            if ($decrypted !== false) {
                $plainSecret = $decrypted;
            }
        }

        $sdkConfig = json_encode([
            'app_key' => $app->app_key,
            'app_secret' => $plainSecret,
            'base_url' => env('app.base_url', 'https://api.example.com'),
        ], JSON_UNESCAPED_UNICODE);

        // 7. 创建任务记录
        $task = ApkInjectTask::create([
            'merchant_id' => $merchantId,
            'app_id' => $appId,
            'task_no' => $taskNo,
            'source_path' => $sourcePath,
            'file_sha256' => $sha256,
            'file_size' => $fileSize,
            'original_filename' => $filename,
            'status' => ApkInjectTask::STATUS_PENDING,
            'progress' => 0,
            'sdk_config' => $sdkConfig,
        ]);

        // 8. 并发计数+1
        $redis->incr($concurrentKey);
        $redis->expire($concurrentKey, 7200); // 2小时过期

        return [
            'task_id' => $task->id,
            'task_no' => $taskNo,
            'upload_url' => $uploadUrl,
        ];
    }

    /**
     * 任务上传完成后投递队列
     * （前端上传到 MinIO 成功后调用此接口通知后端）
     */
    public function dispatchTask(int $taskId, int $merchantId): void
    {
        $task = ApkInjectTask::where('id', $taskId)->where('merchant_id', $merchantId)->find();
        if (!$task) {
            throw new \RuntimeException('任务不存在');
        }
        if ($task->status !== ApkInjectTask::STATUS_PENDING) {
            throw new \RuntimeException('任务状态不允许投递');
        }

        Queue::push('app\job\ApkInjectJob', ['task_id' => $taskId], self::QUEUE_NAME);
    }

    /**
     * 获取下载URL
     */
    public function getDownloadUrl(int $taskId, int $merchantId): string
    {
        $task = ApkInjectTask::where('id', $taskId)->where('merchant_id', $merchantId)->find();
        if (!$task) {
            throw new \RuntimeException('任务不存在');
        }
        if ($task->status !== ApkInjectTask::STATUS_COMPLETED) {
            throw new \RuntimeException('任务未完成，无法下载');
        }
        if (empty($task->output_path)) {
            throw new \RuntimeException('输出文件路径为空');
        }

        return StorageService::getApkPresignedDownloadUrl($task->output_path, 3600);
    }

    /**
     * 任务列表
     */
    public function getList(int $merchantId, int $page = 1, int $pageSize = 15): array
    {
        $total = ApkInjectTask::where('merchant_id', $merchantId)->count();
        $list = ApkInjectTask::where('merchant_id', $merchantId)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        return [
            'total' => $total,
            'list' => $list,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 任务详情
     */
    public function getDetail(int $taskId, int $merchantId): array
    {
        $task = ApkInjectTask::where('id', $taskId)->where('merchant_id', $merchantId)->find();
        if (!$task) {
            throw new \RuntimeException('任务不存在');
        }
        return $task->toArray();
    }

    /**
     * 任务完成后减少并发计数
     */
    public static function decrementConcurrent(int $merchantId): void
    {
        try {
            $redis = Cache::store('redis')->handler();
            $concurrentKey = self::CONCURRENT_KEY . $merchantId;
            $current = (int) $redis->get($concurrentKey);
            if ($current > 0) {
                $redis->decr($concurrentKey);
            }
        } catch (\Exception $e) {
            Log::warning('apk_inject_decrement_concurrent_failed', [
                'merchant_id' => $merchantId,
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
