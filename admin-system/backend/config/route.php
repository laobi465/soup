<?php
// +----------------------------------------------------------------------
// | 路由配置 - 通过 env() 读取，零硬编码
// +----------------------------------------------------------------------

return [
    // 路径分隔符
    'pathinfo_depr'         => '/',
    // URL 伪静态后缀
    'url_html_suffix'       => 'html',
    // URL 普通方式参数
    'url_common_param'      => false,
    // 是否开启路由延迟解析
    'route_delay'           => false,
    // 是否开启路由缓存
    'route_check_cache'     => true,
    // 路由缓存Key
    'route_check_cache_key' => 'admin_route_cache',
    // 路由缓存选项
    'route_cache_option'    => [],
    // 是否强制使用路由
    'route_must'            => false,
    // 域名根
    'url_domain_root'       => env('APP_HOST', '127.0.0.1'),
    // HTTPS 域名检测
    'url_https'             => env('APP_HTTPS', false) ? true : false,

    // 资源路由默认方法
    'request_cache'         => false,
    'request_cache_expire'  => null,
    'request_cache_except'  => [],
    'request_cache_key'     => false,

    // 路由分组
    'route_group'           => [],
    // 路由别名
    'route_alias'           => [],

    // 默认模块/控制器/操作（当路由未匹配时）
    'default_module'        => env('DEFAULT_MODULE', 'admin'),
    'default_controller'    => env('DEFAULT_CONTROLLER', 'Index'),
    'default_action'        => env('DEFAULT_ACTION', 'index'),

    // 是否启用 RestFul
    'restful'               => false,
    // 默认方法
    'default_method'        => 'GET',
];
