<?php
declare (strict_types = 1);

namespace app\controller\api;

use app\BaseController;
use app\model\ApkInjectTask;
use app\model\App;
use app\service\JwtService;
use think\facade\Log;
use think\Request;

/**
 * SDK 鉴权控制器（Task 3 / 支撑 C2）。
 *
 * <p>注入后 APK 首次启动时，SDK 用 manifest 中的 {@code kami_task_token} 调用本接口
 * 换取短期 JWT（默认 1 小时），后续卡密校验请求通过 {@code Authorization: Bearer <token>} 鉴权，
 * 无需 app_secret。
 *
 * <p><b>安全模型</b>：
 * <ul>
 *   <li>本接口为公开接口（不走 ApiAuthMiddleware 的 HMAC 校验），仅校验 task_token 有效性；</li>
 *   <li>task_token 必须对应状态为 COMPLETED 的注入任务（防止未完成任务被滥用）；</li>
 *   <li>签发的 JWT payload 含 task_id/app_id/merchant_id/app_key，<b>不含 app_secret</b>；</li>
 *   <li>JWT 有效期 ≤1 小时，过期后 SDK 需重新用 task_token 换取。</li>
 * </ul>
 */
class SdkAuthController extends BaseController
{
    /**
     * POST /api/v1/sdk/auth
     *
     * 请求体：{ task_token, device_fingerprint, device_name }
     * 响应：{ code: 200, data: { jwt_token, expires_in, app_key, base_url } }
     */
    public function auth(Request $request)
    {
        $taskToken = $request->param('task_token', '');
        $deviceFingerprint = $request->param('device_fingerprint', '');
        $deviceName = $request->param('device_name', '');

        if (empty($taskToken) || strlen($taskToken) !== 64) {
            return $this->apiError(4001, 'task_token 无效');
        }

        if (empty($deviceFingerprint) || strlen($deviceFingerprint) < 8) {
            return $this->apiError(4002, '设备指纹无效');
        }

        $task = ApkInjectTask::where('task_token', $taskToken)->find();
        if (!$task) {
            Log::warning('sdk_auth_task_not_found', ['task_token_prefix' => substr($taskToken, 0, 8)]);
            return $this->apiError(4001, 'task_token 无效');
        }

        if ($task->status != ApkInjectTask::STATUS_COMPLETED) {
            Log::warning('sdk_auth_task_not_completed', [
                'task_id' => $task->id,
                'status' => $task->status,
            ]);
            return $this->apiError(4003, '任务未完成，无法鉴权');
        }

        $app = App::find($task->app_id);
        if (!$app || $app->status != 1) {
            return $this->apiError(4107, '应用已停用');
        }

        // 签发 SDK 会话 JWT（不含 app_secret）
        $jwtService = new JwtService();
        $jwtToken = $jwtService->signSdkSession([
            'task_id'     => $task->id,
            'app_id'      => $task->app_id,
            'merchant_id' => $task->merchant_id,
            'app_key'     => $app->app_key,
            'dfp'         => substr($deviceFingerprint, 0, 64), // 设备指纹（截断防超长）
        ]);

        $baseUrl = env('app.base_url', 'https://api.example.com');

        Log::info('sdk_auth_success', [
            'task_id' => $task->id,
            'app_id'  => $task->app_id,
        ]);

        return $this->apiSuccess([
            'jwt_token'  => $jwtToken,
            'expires_in' => $jwtService->getSdkSessionExpire(),
            'app_key'    => $app->app_key,
            'base_url'   => $baseUrl,
        ]);
    }
}
