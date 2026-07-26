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
    protected int $ipMaxAttempts = 30;
    protected int $lockSeconds = 1800;
    protected string $cachePrefix = 'login_fail:';
    protected string $ipCachePrefix = 'login_fail_ip:';

    public function handle(Request $request, Closure $next): Response
    {
        $username = $request->param('username', '');
        $ip = $request->ip();

        if (!$username) {
            return $next($request);
        }

        $username = strtolower($username);
        $userCacheKey = $this->cachePrefix . $username . ':' . $ip;
        $ipCacheKey = $this->ipCachePrefix . $ip;

        $userLockedUntil = Cache::store('redis')->get($userCacheKey . '_lock');
        $ipLockedUntil = Cache::store('redis')->get($ipCacheKey . '_lock');

        if ($userLockedUntil && time() < $userLockedUntil) {
            $remainSeconds = $userLockedUntil - time();
            return json([
                'code'    => 429,
                'message' => "账号已锁定，请在 {$remainSeconds} 秒后再试",
                'data'    => [
                    'retry_after' => $remainSeconds,
                ],
            ])->code(429);
        }

        if ($ipLockedUntil && time() < $ipLockedUntil) {
            $remainSeconds = $ipLockedUntil - time();
            return json([
                'code'    => 429,
                'message' => "IP已锁定，请在 {$remainSeconds} 秒后再试",
                'data'    => [
                    'retry_after' => $remainSeconds,
                ],
            ])->code(429);
        }

        $response = $next($request);

        $result = $response->getData();
        if (is_array($result) && isset($result['code']) && $result['code'] != 0) {
            $userAttempts = (int) Cache::store('redis')->get($userCacheKey, 0);
            $userAttempts++;
            Cache::store('redis')->set($userCacheKey, $userAttempts, $this->lockSeconds);

            if ($userAttempts >= $this->maxAttempts) {
                Cache::store('redis')->set(
                    $userCacheKey . '_lock',
                    time() + $this->lockSeconds,
                    $this->lockSeconds
                );
            }

            $ipAttempts = (int) Cache::store('redis')->get($ipCacheKey, 0);
            $ipAttempts++;
            Cache::store('redis')->set($ipCacheKey, $ipAttempts, $this->lockSeconds);

            if ($ipAttempts >= $this->ipMaxAttempts) {
                Cache::store('redis')->set(
                    $ipCacheKey . '_lock',
                    time() + $this->lockSeconds,
                    $this->lockSeconds
                );
            }
        }

        return $response;
    }

    public function clear(string $username): void
    {
        $username = strtolower($username);
        $cacheKey = $this->cachePrefix . $username;
        Cache::store('redis')->delete($cacheKey);
        Cache::store('redis')->delete($cacheKey . '_lock');
    }
}
