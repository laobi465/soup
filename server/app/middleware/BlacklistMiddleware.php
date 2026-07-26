<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use app\service\RiskControlService;
use app\model\RiskBlacklist;

class BlacklistMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        $ipCheck = RiskControlService::checkBlacklist(RiskBlacklist::TYPE_IP, $ip);
        if ($ipCheck['is_blacklisted']) {
            return json([
                'code' => 403,
                'message' => 'IP已被封禁：' . ($ipCheck['reason'] ?: '违反平台规则'),
                'data' => null,
            ])->code(403);
        }

        $deviceFingerprint = $request->header('X-Device-Fingerprint', '');
        if ($deviceFingerprint) {
            $deviceCheck = RiskControlService::checkBlacklist(RiskBlacklist::TYPE_DEVICE, $deviceFingerprint);
            if ($deviceCheck['is_blacklisted']) {
                return json([
                    'code' => 403,
                    'message' => '设备已被封禁：' . ($deviceCheck['reason'] ?: '违反平台规则'),
                    'data' => null,
                ])->code(403);
            }
        }

        return $next($request);
    }
}
