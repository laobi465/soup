<?php
// +----------------------------------------------------------------------
// | 数据库配置 - 通过 env() 读取，零硬编码
// +----------------------------------------------------------------------

return [
    // 默认数据库连接
    'default'         => env('DB_TYPE', 'mysql'),

    // 数据库连接配置
    'connections'     => [
        'mysql' => [
            // 数据库类型
            'type'            => env('DB_TYPE', 'mysql'),
            // 服务器地址
            'hostname'        => env('DB_HOSTNAME', '127.0.0.1'),
            // 数据库名
            'database'        => env('DB_DATABASE', 'admin_system'),
            // 用户名
            'username'        => env('DB_USERNAME', 'root'),
            // 密码
            'password'        => env('DB_PASSWORD', ''),
            // 端口
            'hostport'        => env('DB_HOSTPORT', '3306'),
            // 数据库编码
            'charset'         => env('DB_CHARSET', 'utf8mb4'),
            // 数据库表前缀
            'prefix'          => env('DB_PREFIX', 'as_'),
            // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
            'deploy'          => (int) env('DB_DEPLOY', 0),
            // 数据库读写是否分离 主从式有效
            'rw_separate'     => env('DB_RW_SEPARATE', false) ? true : false,
            // 读写分离后 主服务器数量
            'master_num'      => 1,
            // 指定从服务器序号
            'slave_no'        => '',
            // 是否严格检查字段是否存在
            'fields_strict'   => true,
            // 是否需要断线重连
            'break_reconnect' => env('DB_BREAK_RECONNECT', true) ? true : false,
            // 监听SQL
            'trigger_sql'     => env('DB_TRIGGER_SQL', true) ? true : false,
            // 字段缓存
            'fields_cache'    => env('DB_FIELDS_CACHE', false) ? true : false,
            // 最大重试次数
            'max_retry'       => (int) env('DB_MAX_RETRY', 3),
            // 时间字段取出后的默认时间格式
            'datetime_format'  => 'Y-m-d H:i:s',
            // 是否启用 JSON 类型
            'json_assoc'      => true,
        ],

        // 示例：可扩展从库或其他连接
        // 'slave' => [
        //     'type' => env('DB_TYPE_SLAVE', 'mysql'),
        //     'hostname' => env('DB_HOSTNAME_SLAVE', ''),
        //     'database' => env('DB_DATABASE_SLAVE', ''),
        //     'username' => env('DB_USERNAME_SLAVE', ''),
        //     'password' => env('DB_PASSWORD_SLAVE', ''),
        //     'hostport' => env('DB_HOSTPORT_SLAVE', '3306'),
        // ],
    ],

    // 数据库连接参数
    'params' => [
        // PDO 连接属性
        \PDO::ATTR_CASE              => \PDO::CASE_NATURAL,
        \PDO::ATTR_ERRMODE           => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_ORACLE_NULLS      => \PDO::NULL_NATURAL,
        \PDO::ATTR_STRINGIFY_FETCHES => false,
        \PDO::ATTR_EMULATE_PREPARES  => false,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ],
];
