<?php
// +----------------------------------------------------------------------
// | 缓存配置 - 通过 env() 读取，零硬编码
// +----------------------------------------------------------------------

return [
    // 默认驱动
    'default' => env('CACHE_DRIVER', 'file'),

    // 缓存驱动列表
    'stores'  => [
        // 文件缓存
        'file' => [
            'type'       => 'File',
            'path'       => runtime_path() . 'cache' . DIRECTORY_SEPARATOR,
            'prefix'     => env('CACHE_PREFIX', 'admin_cache_'),
            'expire'     => (int) env('CACHE_EXPIRE', 3600),
            'serialize'  => true,
        ],

        // Redis 缓存
        'redis' => [
            'type'       => 'redis',
            'host'       => env('REDIS_HOST', '127.0.0.1'),
            'port'       => (int) env('REDIS_PORT', 6379),
            'password'   => env('REDIS_PASSWORD', ''),
            'select'     => (int) env('REDIS_SELECT', 0),
            'timeout'    => (float) env('REDIS_TIMEOUT', 5),
            'persistent' => env('REDIS_PERSISTENT', false) ? true : false,
            'prefix'     => env('CACHE_PREFIX', 'admin_cache_'),
            'expire'     => (int) env('CACHE_EXPIRE', 3600),
            'serialize'  => true,
        ],

        // 内存缓存（仅开发调试）
        'array' => [
            'type'       => 'array',
            'expire'     => (int) env('CACHE_EXPIRE', 3600),
            'prefix'     => env('CACHE_PREFIX', 'admin_cache_'),
        ],
    ],
];
