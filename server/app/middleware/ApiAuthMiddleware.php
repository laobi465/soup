<?php
declare (strict_types = 1);

namespace app\middleware;

use app\library\AesEncrypt;
use app\model\App;
use think\facade\Cache;
use think\Request;
use think\Response;

class ApiAuthMiddleware
{
    const NONCE_TTL = 600;
    const TIMESTAMP_TOLERANCE = 300;

    public function handle(Request $request, \Closure $next): Response
    {
        $appKey = $request->header('X-AppKey', '');
        $timestamp = $request->header('X-Timestamp', '');
        $nonce = $request->header('X-Nonce', '');
        $sign = $request->header('X-Sign', '');

        if (empty($appKey)) {
            return $this->errorResponse(4001, 'AppKey不能为空');
        }

        $app = App::where('app_key', $appKey)->find();
        if (!$app) {
            return $this->errorResponse(4001, 'AppKey无效');
        }

        if (empty($timestamp) || !is_numeric($timestamp)) {
            return $this->errorResponse(4002, '时间戳无效');
        }

        $now = time();
        if (abs($now - intval($timestamp)) > self::TIMESTAMP_TOLERANCE) {
            return $this->errorResponse(4002, '时间戳已过期');
        }

        if (empty($nonce) || strlen($nonce) < 8) {
            return $this->errorResponse(4003, 'Nonce无效');
        }

        $nonceKey = 'api_nonce:' . $appKey . ':' . $nonce;
        try {
            $redis = Cache::store('redis')->handler();
            $result = $redis->set($nonceKey, 1, ['nx', 'ex' => self::NONCE_TTL]);
            if (!$result) {
                return $this->errorResponse(4003, 'Nonce重复');
            }
        } catch (\Exception $e) {
            if (Cache::has($nonceKey)) {
                return $this->errorResponse(4003, 'Nonce重复');
            }
            Cache::set($nonceKey, 1, self::NONCE_TTL);
        }

        if (empty($sign)) {
            return $this->errorResponse(4001, '签名不能为空');
        }

        $appSecret = AesEncrypt::decrypt($app->app_secret_encrypted);
        if (!$appSecret) {
            return $this->errorResponse(500, 'AppSecret配置错误');
        }

        $method = strtoupper($request->method());
        $path = $request->baseUrl();
        $body = file_get_contents('php://input');
        if ($body === false) {
            $body = '';
        }

        $signString = $method . $path . $timestamp . $nonce . $body;
        $calculatedSign = hash_hmac('sha256', $signString, $appSecret);

        if (!hash_equals($calculatedSign, strtolower($sign))) {
            return $this->errorResponse(4001, '签名错误');
        }

        $ipWhitelist = $app->ip_whitelist;
        if (!empty($ipWhitelist) && is_array($ipWhitelist) && count($ipWhitelist) > 0) {
            $clientIp = $request->ip();
            $ipAllowed = false;
            foreach ($ipWhitelist as $allowedIp) {
                $allowedIp = trim($allowedIp);
                if (empty($allowedIp)) {
                    continue;
                }
                if (strpos($allowedIp, '/') !== false) {
                    if ($this->ipInCidr($clientIp, $allowedIp)) {
                        $ipAllowed = true;
                        break;
                    }
                } elseif (strpos($allowedIp, '*') !== false) {
                    if ($this->ipMatchWildcard($clientIp, $allowedIp)) {
                        $ipAllowed = true;
                        break;
                    }
                } else {
                    if ($clientIp === $allowedIp) {
                        $ipAllowed = true;
                        break;
                    }
                }
            }
            if (!$ipAllowed) {
                return $this->errorResponse(4004, 'IP不在白名单');
            }
        }

        if ($app->status != 1) {
            return $this->errorResponse(4107, '应用已停用');
        }

        $request->app = $app;
        $request->app_id = $app->id;
        $request->merchant_id = $app->merchant_id;
        $request->app_secret = $appSecret;

        return $next($request);
    }

    protected function errorResponse(int $code, string $message): Response
    {
        return json([
            'code' => $code,
            'message' => $message,
            'data' => null,
            'timestamp' => time(),
        ]);
    }

    protected function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - intval($mask));
        return ($ipLong & $maskLong) == ($subnetLong & $maskLong);
    }

    protected function ipMatchWildcard(string $ip, string $pattern): bool
    {
        $ipParts = explode('.', $ip);
        $patternParts = explode('.', $pattern);
        if (count($ipParts) != 4 || count($patternParts) != 4) {
            return false;
        }
        for ($i = 0; $i < 4; $i++) {
            if ($patternParts[$i] === '*') {
                continue;
            }
            if ($ipParts[$i] !== $patternParts[$i]) {
                return false;
            }
        }
        return true;
    }
}
