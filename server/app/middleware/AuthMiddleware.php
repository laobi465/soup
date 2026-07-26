<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use app\service\JwtService;
use think\facade\Config;

class AuthMiddleware
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isWhitelist($request)) {
            return $next($request);
        }

        $header = $request->header('Authorization', '');
        $token = $this->jwtService->parseTokenFromHeader($header);

        if (!$token) {
            return $this->unauthorized('请先登录');
        }

        $payload = $this->jwtService->verifyToken($token, false);
        if (!$payload) {
            return $this->unauthorized('Token无效或已过期');
        }

        $userInfo = [
            'user_id'     => $payload['user_id'],
            'username'    => $payload['username'],
            'role_type'   => $payload['role_type'],
            'merchant_id' => $payload['merchant_id'] ?? null,
        ];

        $request->user = $userInfo;
        $request->user_id = $userInfo['user_id'];
        $request->role_type = $userInfo['role_type'];
        $request->merchant_id = $userInfo['merchant_id'];
        $request->token = $token;

        return $next($request);
    }

    protected function isWhitelist(Request $request): bool
    {
        $whitelist = Config::get('permission.whitelist', []);
        $path = $request->pathinfo();

        foreach ($whitelist as $item) {
            if (str_starts_with($path, $item)) {
                return true;
            }
        }

        return false;
    }

    protected function unauthorized(string $message = '未登录或登录已过期'): Response
    {
        return json([
            'code'    => 401,
            'message' => $message,
            'data'    => null,
        ])->code(401);
    }
}
