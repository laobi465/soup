<?php
// +----------------------------------------------------------------------
// | Redis 配置 - 通过 env() 读取，零硬编码
// +----------------------------------------------------------------------

return [
    // 默认连接
    'default' => 'default',

    // 连接池配置
    'connections' => [
        'default' => [
            // 驱动类型
            'type'        => 'redis',
            // 主机地址
            'host'        => env('REDIS_HOST', '127.0.0.1'),
            // 端口
            'port'        => (int) env('REDIS_PORT', 6379),
            // 密码
            'password'    => env('REDIS_PASSWORD', ''),
            // 数据库编号
            'select'      => (int) env('REDIS_SELECT', 0),
            // 超时时间（秒）
            'timeout'     => (float) env('REDIS_TIMEOUT', 5),
            // 是否长连接
            'persistent'  => env('REDIS_PERSISTENT', false) ? true : false,
            // 前缀
            'prefix'      => env('REDIS_PREFIX', 'admin_system_'),
            // 客户端选项
            'options'     => [
                // 序列化方式
                \Redis::OPT_SERIALIZER => \Redis::SERIALIZER_PHP,
                // 读写超时
                \Redis::OPT_READ_TIMEOUT => (int) env('REDIS_TIMEOUT', 5),
            ],
        ],
    ],
];
