<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use think\facade\Cache;

class LoginThrottleMiddleware
{
    protected int $maxAttempts = 10;
    protected int $lockSeconds = 1800;
    protected string $cachePrefix = 'login_fail:';

    public function handle(Request $request, Closure $next): Response
    {
        $username = $request->param('username', '');
        if (!$username) {
            return $next($request);
        }

        $cacheKey = $this->cachePrefix . $username;

        $lockedUntil = Cache::store('redis')->get($cacheKey . '_lock');
        if ($lockedUntil && time() < $lockedUntil) {
            $remainSeconds = $lockedUntil - time();
            return json([
                'code'    => 429,
                'message' => "账号已锁定，请在 {$remainSeconds} 秒后再试",
                'data'    => [
                    'retry_after' => $remainSeconds,
                ],
            ])->code(429);
        }

        $response = $next($request);

        $result = $response->getData();
        if (is_array($result) && isset($result['code']) && $result['code'] != 0) {
            $attempts = (int) Cache::store('redis')->get($cacheKey, 0);
            $attempts++;
            Cache::store('redis')->set($cacheKey, $attempts, $this->lockSeconds);

            if ($attempts >= $this->maxAttempts) {
                Cache::store('redis')->set(
                    $cacheKey . '_lock',
                    time() + $this->lockSeconds,
                    $this->lockSeconds
                );
            }
        }

        return $response;
    }

    public function clear(string $username): void
    {
        $cacheKey = $this->cachePrefix . $username;
        Cache::store('redis')->delete($cacheKey);
        Cache::store('redis')->delete($cacheKey . '_lock');
    }
}
