<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use think\facade\Config;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission = ''): Response
    {
        $user = $request->user;
        if (!$user) {
            return $this->forbidden('请先登录');
        }

        $roleType = $user['role_type'] ?? 0;
        if ($roleType == 1) {
            return $next($request);
        }

        if ($permission) {
            $permissions = Config::get("permission.permissions.{$roleType}", []);
            if (!in_array($permission, $permissions)) {
                return $this->forbidden('无权限访问');
            }
        }

        return $next($request);
    }

    protected function forbidden(string $message = '无权限访问'): Response
    {
        return json([
            'code'    => 403,
            'message' => $message,
            'data'    => null,
        ])->code(403);
    }
}
