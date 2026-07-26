<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\service\JwtService;
use app\model\User;
use app\library\Bcrypt;
use think\Request;
use think\facade\Config;

class AuthController extends BaseController
{
    protected JwtService $jwtService;

    public function __construct(\think\App $app, JwtService $jwtService)
    {
        parent::__construct($app);
        $this->jwtService = $jwtService;
    }

    public function login(Request $request)
    {
        $username = $request->param('username', '');
        $password = $request->param('password', '');

        if (empty($username) || empty($password)) {
            return error('用户名和密码不能为空', 400);
        }

        $user = User::where('username', $username)->find();
        if (!$user) {
            return error('用户名或密码错误', 400);
        }

        if ($user->status != 1) {
            $statusMap = [
                0 => '账号已被禁用',
                2 => '账号已被锁定',
                3 => '账号已过期',
            ];
            return error($statusMap[$user->status] ?? '账号状态异常', 400);
        }

        if (!Bcrypt::verify($password, $user->password_hash)) {
            return error('用户名或密码错误', 400);
        }

        $merchantId = null;
        if (in_array($user->role_type, [3, 4])) {
            $merchant = $user->merchant;
            if ($merchant) {
                $merchantId = $merchant->id;
            }
        }

        $payload = [
            'user_id'     => $user->id,
            'username'    => $user->username,
            'role_type'   => $user->role_type,
            'merchant_id' => $merchantId,
        ];

        $accessToken = $this->jwtService->generateToken($payload, false);
        $refreshToken = $this->jwtService->generateToken($payload, true);

        $user->last_login_ip = $request->ip();
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->login_fail_count = 0;
        $user->lock_until = null;
        $user->save();

        $loginThrottle = app(\app\middleware\LoginThrottleMiddleware::class);
        $loginThrottle->clear($username);

        $userInfo = [
            'id'          => $user->id,
            'username'    => $user->username,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'role_type'   => $user->role_type,
            'role_text'   => $user->role_type_text,
            'avatar'      => $user->avatar,
            'status'      => $user->status,
            'merchant_id' => $merchantId,
        ];

        return success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => Config::get('jwt.expire', 7200),
            'user_info'     => $userInfo,
        ], '登录成功');
    }

    public function logout(Request $request)
    {
        $token = $request->token ?? '';
        if ($token) {
            $this->jwtService->addToBlacklist($token);
        }

        return success(null, '登出成功');
    }

    public function refresh(Request $request)
    {
        $refreshToken = $request->param('refresh_token', '');
        if (empty($refreshToken)) {
            $header = $request->header('Authorization', '');
            $refreshToken = $this->jwtService->parseTokenFromHeader($header) ?? '';
        }

        if (empty($refreshToken)) {
            return error('刷新令牌不能为空', 400);
        }

        $result = $this->jwtService->refreshToken($refreshToken);
        if (!$result) {
            return error('刷新令牌无效或已过期', 401);
        }

        return success($result, '刷新成功');
    }

    public function info(Request $request)
    {
        $userId = $request->user_id ?? 0;
        if (!$userId) {
            return error('用户未登录', 401);
        }

        $user = User::with(['merchant'])->find($userId);
        if (!$user) {
            return error('用户不存在', 404);
        }

        if ($user->status != 1) {
            return error('账号状态异常', 400);
        }

        $roleType = $user->role_type;
        $permissions = Config::get("permission.permissions.{$roleType}", []);
        $menus = $this->getMenusByRole($roleType);

        $merchantInfo = null;
        if ($user->merchant) {
            $merchantInfo = [
                'id'           => $user->merchant->id,
                'merchant_no'  => $user->merchant->merchant_no,
                'merchant_name' => $user->merchant->merchant_name,
                'package_id'   => $user->merchant->package_id,
                'balance'      => $user->merchant->balance,
                'app_quota'    => $user->merchant->app_quota,
                'card_quota'   => $user->merchant->card_quota,
                'card_used'    => $user->merchant->card_used,
            ];
        }

        $userInfo = [
            'id'          => $user->id,
            'username'    => $user->username,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'role_type'   => $user->role_type,
            'role_text'   => $user->role_type_text,
            'avatar'      => $user->avatar,
            'status'      => $user->status,
            'merchant'    => $merchantInfo,
            'created_at'  => $user->created_at,
            'last_login_at' => $user->last_login_at,
            'last_login_ip' => $user->last_login_ip,
        ];

        return success([
            'user_info'   => $userInfo,
            'menus'       => $menus,
            'permissions' => $permissions,
        ]);
    }

    protected function getMenusByRole(int $roleType): array
    {
        $allMenus = Config::get('permission.menus', []);
        $result = [];

        foreach ($allMenus as $menu) {
            if (in_array($roleType, $menu['roles'] ?? [])) {
                $menuItem = [
                    'path'      => $menu['path'],
                    'name'      => $menu['name'],
                    'component' => $menu['component'],
                    'meta'      => $menu['meta'] ?? [],
                ];

                if (isset($menu['children']) && is_array($menu['children'])) {
                    $children = [];
                    foreach ($menu['children'] as $child) {
                        if (in_array($roleType, $child['roles'] ?? [])) {
                            $children[] = [
                                'path'      => $child['path'],
                                'name'      => $child['name'],
                                'component' => $child['component'],
                                'meta'      => $child['meta'] ?? [],
                            ];
                        }
                    }
                    if (!empty($children)) {
                        $menuItem['children'] = $children;
                    }
                }

                $result[] = $menuItem;
            }
        }

        return $result;
    }
}
