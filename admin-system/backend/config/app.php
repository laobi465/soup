<?php
// +----------------------------------------------------------------------
// | 应用配置
// +----------------------------------------------------------------------

use think\Env;

return [
    // 应用名称
    'app_name'        => env('APP_NAME', 'admin-system'),
    // 命名空间
    'app_namespace'   => env('APP_NAMESPACE', 'app'),
    // 应用调试模式
    'app_debug'       => Env::get('APP_DEBUG', false),
    // 应用 Trace
    'app_trace'       => Env::get('APP_TRACE', false),
    // 默认时区
    'default_timezone' => env('APP_DEFAULT_TIMEZONE', 'Asia/Shanghai'),
    // 默认语言
    'default_lang'    => env('APP_DEFAULT_LANG', 'zh-cn'),
    // 默认过滤器
    'default_filter'  => env('APP_DEFAULT_FILTER', 'trim,htmlspecialchars'),
    // 应用地址
    'app_host'        => env('APP_HOST', 'http://127.0.0.1'),
    // 是否 HTTPS
    'app_https'       => env('APP_HTTPS', false) ? true : false,

    // 应用映射
    'app_map'        => [],
    // 域名绑定
    'domain_bind'    => [],
    // 自动多应用
    'auto_multi_app' => false,
    // 默认应用
    'default_app'    => 'admin',

    // 异常处理类
    'exception_handle' => \app\exception\Handle::class,

    // 默认输出类型
    'default_return_type' => 'json',

    // 默认模块
    'default_module'     => 'admin',
    // 默认控制器
    'default_controller' => 'Index',
    // 默认操作
    'default_action'     => 'index',

    // 操作方法后缀
    'action_suffix'      => '',

    // 视图配置路径
    'view_path'          => '',

    // 模板后缀
    'view_suffix'        => 'html',
    // 模板引擎
    'view_engine'        => 'think',

    // 默认跳转页
    'dispatch_success_tmpl' => '',
    'dispatch_error_tmpl'    => '',

    // 默认 AJAX 数据返回格式
    'default_ajax_return' => 'json',

    // 默认 JSONP 处理方法
    'default_jsonp_handler' => 'jsonpReturn',

    // 默认 cookie
    'cookie' => [
        'prefix'    => '',
        'expire'    => 0,
        'path'      => '/',
        'domain'    => '',
        'secure'    => env('APP_HTTPS', false) ? true : false,
        'httponly'  => true,
        'raw'       => false,
        'samesite'  => 'Lax',
    ],

    // 文件缓存路径
    'runtime_path'      => runtime_path(),
    'runtime_option'    => [
        'file' => [
            'path' => runtime_path() . 'cache' . DIRECTORY_SEPARATOR,
        ],
    ],

    // session 配置
    'session' => [
        'prefix'         => env('SESSION_PREFIX', 'admin_system_'),
        'type'           => env('SESSION_TYPE', 'redis'),
        'auto_start'     => true,
        'expire'         => env('SESSION_EXPIRE', 7200),
        'name'           => env('SESSION_NAME', 'ADMIN_SESSION_ID'),
        'cookie'         => [
            'httponly' => true,
            'secure'   => env('APP_HTTPS', false) ? true : false,
            'samesite' => 'Lax',
        ],
        'redis'          => [
            'host'       => env('REDIS_HOST', '127.0.0.1'),
            'port'       => env('REDIS_PORT', 6379),
            'password'   => env('REDIS_PASSWORD', ''),
            'select'     => env('REDIS_SELECT', 0),
            'timeout'    => env('REDIS_TIMEOUT', 5),
            'persistent' => env('REDIS_PERSISTENT', false) ? true : false,
        ],
    ],
];
