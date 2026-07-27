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
    private const CONCURRENT_TTL = 7200; // 并发计数器 2 小时过期（兜底）
    private const QUEUE_NAME = 'apk-inject';

    /**
     * 创建注入任务（不占用并发额度，仅记录任务）
     * 并发计数在 dispatchTask 时原子获取，避免放弃上传导致计数泄漏。
     */
    public function createTask(int $merchantId, int $appId, string $filename, int $fileSize, string $sha256): array
    {
        // 1. 校验应用归属
        $app = App::where('id', $appId)->where('merchant_id', $merchantId)->find();
        if (!$app) {
            throw new \RuntimeException('应用不存在或无权限');
        }

        // 2. 并发限制预校验（软校验，真实原子校验在 dispatchTask）
        // 此处仅用于快速拒绝明显超限的请求，不依赖此校验保证原子性
        $redis = Cache::store('redis')->handler();
        $concurrentKey = self::CONCURRENT_KEY . $merchantId;
        $current = (int) $redis->get($concurrentKey);
        if ($current >= self::MAX_CONCURRENT) {
            throw new \RuntimeException('并发任务数超限，请等待已有任务完成');
        }

        // 3. SHA-256 去重（24小时内）
        // 用 Redis 分布锁防止并发请求绕过去重 (C6)
        $dedupLockKey = 'apk_dedup:' . $sha256 . ':' . $merchantId;
        $lockToken = bin2hex(random_bytes(8));
        $redis = Cache::store('redis')->handler();
        $lockAcquired = $redis->set($dedupLockKey, $lockToken, ['NX', 'EX' => 5]);
        if (!$lockAcquired) {
            throw new \RuntimeException('该APK正在处理中，请稍后重试');
        }

        try {
            $existing = ApkInjectTask::where('file_sha256', $sha256)
                ->where('merchant_id', $merchantId)
                ->where('created_at', '>', date('Y-m-d H:i:s', time() - self::DEDUP_WINDOW))
                ->whereIn('status', [ApkInjectTask::STATUS_PENDING, ApkInjectTask::STATUS_PROCESSING, ApkInjectTask::STATUS_COMPLETED])
                ->find();
            if ($existing) {
                throw new \RuntimeException('该APK在24小时内已提交过注入任务');
            }

            // 4. 生成任务编号和存储路径 (M5: mt_rand → random_int)
            $taskNo = 'APK' . date('YmdHis') . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $sourcePath = sprintf('apk-source/%s/%s.apk', date('Ymd'), $taskNo);

            // 5. 生成 presigned 上传 URL
            $uploadUrl = StorageService::getApkPresignedUploadUrl($sourcePath, 300);

            // 6. 生成 task_token（替代明文 app_secret，见 Task 2）
            $taskToken = bin2hex(random_bytes(32));

            // 7. SDK 配置只存非敏感信息（app_secret 不落库，Job 执行时实时解密取用）
            $sdkConfig = json_encode([
                'app_key' => $app->app_key,
                'base_url' => env('app.base_url', 'https://api.example.com'),
            ], JSON_UNESCAPED_UNICODE);

            // 8. 创建任务记录（不 INCR 并发计数，dispatchTask 时才占用额度）
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
                'task_token' => $taskToken,
                'sdk_config' => $sdkConfig,
            ]);

            return [
                'task_id' => $task->id,
                'task_no' => $taskNo,
                'upload_url' => $uploadUrl,
            ];
        } finally {
            // Lua 脚本释放锁 (仅释放自己的, 避免误删其他请求的锁)
            $lua = "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end";
            $redis->eval($lua, [$dedupLockKey, $lockToken], 1);
        }
    }

    /**
     * 任务上传完成后投递队列
     * （前端上传到 MinIO 成功后调用此接口通知后端）
     * 并发计数在此处原子获取，避免放弃上传导致计数泄漏。
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

        // 校验文件已上传到 MinIO 且大小一致（防止未上传或大小不符就投递）
        $fileInfo = StorageService::getApkStorageDriver()->getFileInfo($task->source_path);
        if (empty($fileInfo)) {
            throw new \RuntimeException('文件未上传或不存在，请先上传APK');
        }
        if ((int)($fileInfo['size'] ?? 0) !== (int)$task->file_size) {
            throw new \RuntimeException(sprintf(
                '文件大小不匹配：声明 %d 字节，实际上传 %d 字节',
                $task->file_size,
                $fileInfo['size'] ?? 0
            ));
        }

        // 原子获取并发槽位（Lua 脚本：INCR→若 >MAX 则 DECR 回退→否则 EXPIRE）
        if (!$this->acquireConcurrentSlot($merchantId)) {
            throw new \RuntimeException('并发任务数超限，请等待已有任务完成');
        }

        try {
            Queue::push('app\job\ApkInjectJob', ['task_id' => $taskId], self::QUEUE_NAME);
        } catch (\Exception $e) {
            // 投队列失败，回退并发计数
            self::decrementConcurrent($merchantId);
            throw new \RuntimeException('任务投递失败: ' . $e->getMessage());
        }
    }

    /**
     * 原子获取并发槽位（Lua 脚本保证 INCR 与超限回退的原子性）
     * 返回 true 表示获取成功，false 表示已达上限
     */
    private function acquireConcurrentSlot(int $merchantId): bool
    {
        $redis = Cache::store('redis')->handler();
        $concurrentKey = self::CONCURRENT_KEY . $merchantId;

        // Lua 脚本：原子 INCR，超限则 DECR 回退并返回 -1
        $lua = <<<'LUA'
local c = redis.call('INCR', KEYS[1])
if c > tonumber(ARGV[1]) then
    redis.call('DECR', KEYS[1])
    return -1
end
redis.call('EXPIRE', KEYS[1], ARGV[2])
return c
LUA;

        $result = $redis->eval($lua, [$concurrentKey, self::MAX_CONCURRENT, self::CONCURRENT_TTL], 1);
        return $result !== false && (int) $result > 0;
    }

    /**
     * 获取下载URL
     * M8: 商户级限流（10 次/小时），防止刷接口放大 MinIO 负载
     */
    public function getDownloadUrl(int $taskId, int $merchantId): string
    {
        // M8: 商户级下载限流（10 次/小时）
        $redis = Cache::store('redis')->handler();
        $limitKey = 'apk_inject:dl_limit:' . $merchantId;
        $count = (int) $redis->get($limitKey);
        if ($count >= 10) {
            throw new \RuntimeException('下载链接获取过于频繁，请稍后再试');
        }
        // 原子 INCR，首次设置 EXPIRE
        $lua = <<<'LUA'
local c = redis.call('INCR', KEYS[1])
if c == 1 then
    redis.call('EXPIRE', KEYS[1], ARGV[1])
end
return c
LUA;
        $redis->eval($lua, [$limitKey, 3600], 1);

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
     * 任务详情（屏蔽 sdk_config 敏感字段）
     */
    public function getDetail(int $taskId, int $merchantId): array
    {
        $task = ApkInjectTask::where('id', $taskId)->where('merchant_id', $merchantId)->find();
        if (!$task) {
            throw new \RuntimeException('任务不存在');
        }
        $data = $task->toArray();
        // 二次保险：即使模型 $hidden 失效也不泄露 sdk_config
        unset($data['sdk_config']);
        return $data;
    }

    /**
     * 原子减少并发计数（Lua 脚本保证 DECR 与防负数回补的原子性）
     * 在任务终态（完成/失败）时调用
     */
    public static function decrementConcurrent(int $merchantId): void
    {
        try {
            $redis = Cache::store('redis')->handler();
            $concurrentKey = self::CONCURRENT_KEY . $merchantId;

            // Lua 脚本：原子 DECR，若结果 <0 则 INCR 回补（防计数器欠账）
            $lua = <<<'LUA'
local c = redis.call('DECR', KEYS[1])
if c < 0 then
    redis.call('INCR', KEYS[1])
    return 0
end
return c
LUA;

            $redis->eval($lua, [$concurrentKey], 1);
        } catch (\Exception $e) {
            Log::warning('apk_inject_decrement_concurrent_failed', [
                'merchant_id' => $merchantId,
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
