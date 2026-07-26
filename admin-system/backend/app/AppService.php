<?php
// +----------------------------------------------------------------------
// | admin-system 应用服务
// +----------------------------------------------------------------------
// | 由 think\App 在 initialize() 时调用，用于注册应用级服务
// +----------------------------------------------------------------------

namespace app;

use think\Service;

/**
 * 应用服务
 * 负责：
 *  1. 注册全局辅助函数（在 composer autoload files 中已注册 helpers.php）
 *  2. 注册自定义中间件、验证规则、命令等扩展
 *  3. 注册业务事件监听器
 */
class AppService extends Service
{
    /**
     * 注册服务
     * 此方法在应用启动时执行，仅做注册不做实际运行
     */
    public function register(): void
    {
        // 服务注册（仅做容器绑定，避免执行实际逻辑）
        // JWT 服务
        $this->app->bind('jwt', \app\service\JwtService::class);
        // 鉴权服务
        $this->app->bind('auth', \app\service\AuthService::class);
        // 用户服务
        $this->app->bind('user_service', \app\service\UserService::class);
        // 角色服务
        $this->app->bind('role_service', \app\service\RoleService::class);
        // 菜单服务
        $this->app->bind('menu_service', \app\service\MenuService::class);
    }

    /**
     * 启动服务
     * 此方法在所有服务注册完成后执行
     */
    public function boot(): void
    {
        // 注册自定义验证规则
        $this->registerValidationRules();
    }

    /**
     * 注册自定义验证规则
     */
    protected function registerValidationRules(): void
    {
        // 验证密码强度
        \think\facade\Validate::extend('strongPassword', function ($value) {
            if (!is_string($value) || strlen($value) < 8) {
                return false;
            }
            // 至少包含数字 + 字母
            return (bool) preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $value);
        });

        // 验证权限标识格式
        \think\facade\Validate::extend('permissionNode', function ($value) {
            return (bool) preg_match('/^[a-z]+(:[a-z]+){1,3}$/i', (string) $value);
        });
    }
}
