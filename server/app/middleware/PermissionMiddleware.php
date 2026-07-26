<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use think\facade\Config;
use app\model\User;
use app\model\SubRole;

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
            if ($roleType == 4) {
                $userId = $user['user_id'] ?? 0;
                $userModel = User::find($userId);
                if (!$userModel || empty($userModel->sub_role_id)) {
                    return $this->forbidden('无权限访问');
                }

                $subRole = SubRole::find($userModel->sub_role_id);
                if (!$subRole || empty($subRole->permissions)) {
                    return $this->forbidden('无权限访问');
                }

                if (!in_array($permission, $subRole->permissions)) {
                    return $this->forbidden('无权限访问');
                }
            } else {
                $permissions = Config::get("permission.permissions.{$roleType}", []);
                if (!in_array($permission, $permissions)) {
                    return $this->forbidden('无权限访问');
                }
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
