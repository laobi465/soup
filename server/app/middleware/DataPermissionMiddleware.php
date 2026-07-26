<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use app\model\User;

class DataPermissionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user_id;
        $roleType = $request->role_type;

        if (!$userId || $roleType != 4) {
            return $next($request);
        }

        $user = User::find($userId);
        if (!$user) {
            return $next($request);
        }

        $appIds = $user->app_ids;
        if (!empty($appIds) && is_array($appIds)) {
            $request->app_ids = $appIds;
        } else {
            $request->app_ids = [];
        }

        $request->sub_role_id = $user->sub_role_id;

        return $next($request);
    }
}
