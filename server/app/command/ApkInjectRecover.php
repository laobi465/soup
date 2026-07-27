<?php
declare (strict_types = 1);

namespace app\command;

use app\model\ApkInjectTask;
use app\service\ApkInjectService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

/**
 * APK 注入任务卡死恢复命令（Task 4 / C6, C7）。
 *
 * <p>定时扫描两类超时任务并回收：
 * <ul>
 *   <li><b>PROCESSING 卡死</b>（C6）：{@code status=PROCESSING AND updated_at < NOW() - 15分钟}
 *       —— Worker 崩溃/重启导致任务永久停留在处理中，并发计数泄漏。</li>
 *   <li><b>PENDING 放弃上传</b>（C7）：{@code status=PENDING AND created_at < NOW() - 10分钟}
 *       —— 用户创建任务后从未调用 dispatch，占用任务表空间（注：并发计数在 dispatch 时
 *       才获取，故此类任务不涉及并发计数回收）。</li>
 * </ul>
 *
 * <p>调度：每 5 分钟执行一次（由 docker-compose 的 scheduler 服务循环触发）。
 * 单次扫描限 100 条，避免大表长事务。
 */
class ApkInjectRecover extends Command
{
    /** PROCESSING 超时阈值（秒） */
    private const PROCESSING_TIMEOUT = 900; // 15 分钟

    /** PENDING 超时阈值（秒） */
    private const PENDING_TIMEOUT = 600; // 10 分钟

    /** 单次扫描上限 */
    private const BATCH_LIMIT = 100;

    protected function configure()
    {
        $this->setName('apk-inject:recover')
            ->setDescription('回收卡死的 APK 注入任务（PROCESSING>15min / PENDING>10min）');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->info('=== APK 注入任务回收开始 ===');

        $processingRecovered = $this->recoverProcessingTasks($output);
        $pendingRecovered = $this->recoverPendingTasks($output);

        $output->info(sprintf(
            '回收完成：PROCESSING %d 条，PENDING %d 条',
            $processingRecovered,
            $pendingRecovered
        ));
        $output->info('=== APK 注入任务回收结束 ===');

        return 0;
    }

    /**
     * 回收卡死的 PROCESSING 任务（C6）
     * Worker 崩溃后任务永久停留处理中，需标记失败并回收并发计数
     */
    private function recoverProcessingTasks(Output $output): int
    {
        $threshold = date('Y-m-d H:i:s', time() - self::PROCESSING_TIMEOUT);

        $tasks = ApkInjectTask::where('status', ApkInjectTask::STATUS_PROCESSING)
            ->where('updated_at', '<', $threshold)
            ->limit(self::BATCH_LIMIT)
            ->select();

        $count = 0;
        foreach ($tasks as $task) {
            try {
                $task->update([
                    'status' => ApkInjectTask::STATUS_FAILED,
                    'error_log' => sprintf('任务处理超时（>%d 分钟未更新，由回收命令标记失败）', self::PROCESSING_TIMEOUT / 60),
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);

                // 回收并发计数（C6 核心：防止计数泄漏）
                ApkInjectService::decrementConcurrent($task->merchant_id);

                $count++;
                Log::info('apk_inject_recover_processing', [
                    'task_id' => $task->id,
                    'merchant_id' => $task->merchant_id,
                ]);
                $output->writeln(sprintf('  [PROCESSING] task #%d 已标记失败并回收并发', $task->id));
            } catch (\Exception $e) {
                Log::error('apk_inject_recover_processing_failed', [
                    'task_id' => $task->id,
                    'msg' => $e->getMessage(),
                ]);
                $output->error(sprintf('  [PROCESSING] task #%d 回收失败: %s', $task->id, $e->getMessage()));
            }
        }

        return $count;
    }

    /**
     * 回收放弃上传的 PENDING 任务（C7）
     * 用户创建任务后未调用 dispatch，INCR 已移到 dispatch 故不涉及并发回收
     */
    private function recoverPendingTasks(Output $output): int
    {
        $threshold = date('Y-m-d H:i:s', time() - self::PENDING_TIMEOUT);

        $tasks = ApkInjectTask::where('status', ApkInjectTask::STATUS_PENDING)
            ->where('created_at', '<', $threshold)
            ->limit(self::BATCH_LIMIT)
            ->select();

        $count = 0;
        foreach ($tasks as $task) {
            try {
                $task->update([
                    'status' => ApkInjectTask::STATUS_FAILED,
                    'error_log' => sprintf('上传超时未提交（>%d 分钟未 dispatch，由回收命令标记失败）', self::PENDING_TIMEOUT / 60),
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);

                // 注：并发计数在 dispatchTask 时才获取，PENDING 任务未占用并发额度，无需 decrement
                $count++;
                Log::info('apk_inject_recover_pending', [
                    'task_id' => $task->id,
                    'merchant_id' => $task->merchant_id,
                ]);
                $output->writeln(sprintf('  [PENDING] task #%d 已标记失败（上传超时）', $task->id));
            } catch (\Exception $e) {
                Log::error('apk_inject_recover_pending_failed', [
                    'task_id' => $task->id,
                    'msg' => $e->getMessage(),
                ]);
                $output->error(sprintf('  [PENDING] task #%d 回收失败: %s', $task->id, $e->getMessage()));
            }
        }

        return $count;
    }
}
