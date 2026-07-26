<?php
declare (strict_types = 1);

namespace app\middleware;

use think\facade\Cache;
use think\Request;
use think\Response;

class ApiRateLimitMiddleware
{
    const WINDOW_SECONDS = 60;

    protected array $limits = [
        'card' => 60,
        'ip' => 300,
        'device' => 60,
        'app' => 1000,
        'merchant' => 5000,
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        $appId = $request->app_id ?? 0;
        $merchantId = $request->merchant_id ?? 0;
        $ip = $request->ip();

        $cardNo = $request->param('card_no', '');
        $deviceFingerprint = $request->param('device_fingerprint', '');

        $limits = $this->getLimits($appId);

        $checks = [
            'app' => $appId ? 'app:' . $appId : null,
            'merchant' => $merchantId ? 'merchant:' . $merchantId : null,
            'ip' => 'ip:' . $ip,
        ];

        if ($cardNo) {
            $checks['card'] = 'card:' . md5($cardNo);
        }

        if ($deviceFingerprint) {
            $checks['device'] = 'device:' . md5($deviceFingerprint);
        }

        foreach ($checks as $type => $keySuffix) {
            if (!$keySuffix) {
                continue;
            }
            $limit = $limits[$type] ?? ($this->limits[$type] ?? 100);
            $key = 'ratelimit:' . $type . ':' . $keySuffix . ':' . floor(time() / self::WINDOW_SECONDS);
            $current = Cache::get($key, 0);
            if ($current >= $limit) {
                $resetIn = self::WINDOW_SECONDS - (time() % self::WINDOW_SECONDS);
                return $this->errorResponse(4005, '请求超限，请稍后再试', [
                    'retry_after' => $resetIn,
                    'limit' => $limit,
                    'type' => $type,
                ]);
            }
        }

        $response = $next($request);

        foreach ($checks as $type => $keySuffix) {
            if (!$keySuffix) {
                continue;
            }
            $key = 'ratelimit:' . $type . ':' . $keySuffix . ':' . floor(time() / self::WINDOW_SECONDS);
            $current = Cache::get($key, 0);
            $current++;
            Cache::set($key, $current, self::WINDOW_SECONDS * 2);
        }

        return $response;
    }

    protected function getLimits(int $appId): array
    {
        $limits = $this->limits;
        $configKey = 'api_rate_limit_' . $appId;
        $customLimits = Cache::get($configKey);
        if ($customLimits && is_array($customLimits)) {
            $limits = array_merge($limits, $customLimits);
        }
        return $limits;
    }

    protected function errorResponse(int $code, string $message, array $data = []): Response
    {
        return json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
        ])->code(429);
    }
}
