<?php
declare (strict_types = 1);

namespace app\job;

use app\model\ApkInjectTask;
use app\service\ApkInjectService;
use think\facade\Log;
use think\queue\Job;

class ApkInjectJob
{
    /**
     * 队列任务执行入口
     */
    public function fire(Job $job, $data): void
    {
        $taskId = $data['task_id'] ?? 0;
        if (empty($taskId)) {
            $job->delete();
            return;
        }

        $task = ApkInjectTask::find($taskId);
        if (!$task) {
            Log::error('apk_inject_job_task_not_found', ['task_id' => $taskId]);
            $job->delete();
            return;
        }

        // 幂等校验：只处理排队中的任务
        if ($task->status != ApkInjectTask::STATUS_PENDING) {
            $job->delete();
            return;
        }

        // 更新状态为处理中
        $task->update([
            'status' => ApkInjectTask::STATUS_PROCESSING,
            'progress' => 10,
        ]);

        try {
            // 调用 Java 注入微服务
            $result = $this->callInjectService($task);

            if ($result['success']) {
                $task->update([
                    'status' => ApkInjectTask::STATUS_COMPLETED,
                    'progress' => 100,
                    'output_path' => $result['output_path'],
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $task->update([
                    'status' => ApkInjectTask::STATUS_FAILED,
                    'error_log' => $result['error'] ?? '未知错误',
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('apk_inject_job_error', [
                'task_id' => $taskId,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $task->update([
                'status' => ApkInjectTask::STATUS_FAILED,
                'error_log' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
        } finally {
            // 减少并发计数
            ApkInjectService::decrementConcurrent($task->merchant_id);
        }

        $job->delete();
    }

    /**
     * 调用 Java 注入微服务
     */
    private function callInjectService(ApkInjectTask $task): array
    {
        $sdkConfig = $task->sdk_config;
        if (!is_array($sdkConfig)) {
            $sdkConfig = json_decode((string)$task->sdk_config, true) ?: [];
        }

        $payload = [
            'task_id' => $task->id,
            'source_path' => $task->source_path,
            'app_key' => $sdkConfig['app_key'] ?? '',
            'app_secret' => $sdkConfig['app_secret'] ?? '',
            'base_url' => $sdkConfig['base_url'] ?? '',
        ];

        $injectServiceUrl = env('apk_inject.service_url', 'http://apk-inject-service:8080');

        $client = new \GuzzleHttp\Client([
            'timeout' => 180,
            'connect_timeout' => 10,
        ]);

        $response = $client->post($injectServiceUrl . '/api/v1/inject', [
            'json' => $payload,
        ]);

        $body = json_decode($response->getBody()->getContents(), true);

        return [
            'success' => $body['success'] ?? false,
            'output_path' => $body['output_path'] ?? '',
            'error' => $body['error'] ?? '',
        ];
    }

    /**
     * 任务失败回调
     */
    public function failed($data): void
    {
        $taskId = $data['task_id'] ?? 0;
        $task = ApkInjectTask::find($taskId);
        if ($task && $task->status == ApkInjectTask::STATUS_PROCESSING) {
            $task->update([
                'status' => ApkInjectTask::STATUS_FAILED,
                'error_log' => '队列任务执行失败（超时或异常）',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            ApkInjectService::decrementConcurrent($task->merchant_id);
        }

        Log::error('apk_inject_job_failed', ['task_id' => $taskId]);
    }
}
