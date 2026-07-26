<?php
// +----------------------------------------------------------------------
// | 日志配置 - 通过 env() 读取，零硬编码
// +----------------------------------------------------------------------

return [
    // 默认日志通道
    'default'  => env('LOG_TYPE', 'file'),

    // 日志通道列表
    'channels' => [
        // 文件日志
        'file' => [
            'type'           => 'file',
            // 日志保存路径
            'path'           => env('LOG_PATH', runtime_path() . 'log' . DIRECTORY_SEPARATOR),
            // 日志等级
            'level'          => env('LOG_LEVEL', 'info'),
            // 单文件最大容量
            'max_files'      => (int) env('LOG_MAX_FILES', 30),
            // 日志独立级别（不同等级写入不同文件）
            'apart_level'    => env('LOG_APART_LEVEL', false) ? true : false,
            // 单文件日志写入
            'single'         => false,
            // 实时写入
            'realtime_write' => true,
            // 日志格式
            'format'         => env('LOG_FORMAT', '[%datetime%][%level_name%] %message% %context% %extra%\n'),
            // 是否处理最大日志行数
            'max_files_count'=> (int) env('LOG_MAX_FILES', 30),
            // 是否启用 JSON 格式
            'json'           => false,
            // 是否记录 process_id
            'process_id'     => false,
            // 是否记录调用来源
            'apart_level_key' => 'level',
        ],

        // Redis 日志
        'redis' => [
            'type'       => 'redis',
            'host'       => env('REDIS_HOST', '127.0.0.1'),
            'port'       => (int) env('REDIS_PORT', 6379),
            'password'   => env('REDIS_PASSWORD', ''),
            'select'     => (int) env('REDIS_SELECT', 0),
            'timeout'    => (float) env('REDIS_TIMEOUT', 5),
            'prefix'     => env('REDIS_PREFIX', 'admin_system_') . 'log_',
            'level'      => env('LOG_LEVEL', 'info'),
            'apart_level'=> env('LOG_APART_LEVEL', false) ? true : false,
            'format'     => env('LOG_FORMAT', '[%datetime%][%level_name%] %message% %context% %extra%\n'),
        ],
    ],

    // 关闭全局日志写入
    'close'    => false,
    // 是否记录异常 trace
    'exception_handle' => true,
    // 全局忽略的日志等级
    'ignore_level' => [],
];
