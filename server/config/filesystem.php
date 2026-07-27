<?php

return [
    // 默认磁盘
    'default' => 'local',
    // 磁盘列表
    'disks'   => [
        'local'  => [
            'type' => 'local',
            'root' => app()->getRuntimePath() . 'storage',
        ],
        'public' => [
            // 磁盘类型
            'type'       => 'local',
            // 磁盘路径
            'root'       => app()->getRootPath() . 'public/storage',
            // 磁盘路径对应的外部URL路径
            'url'        => '/storage',
            // 可见性
            'visibility' => 'public',
        ],
        'minio' => [
            'type'       => 'minio',
            'endpoint'    => env('minio.endpoint', 'http://minio:9000'),
            'bucket'      => env('minio.bucket', 'card-auth'),
            'access_key'  => env('minio.access_key', 'minioadmin'),
            'secret_key'  => env('minio.secret_key', 'minioadmin123'),
            'use_ssl'     => false,
            'region'      => 'us-east-1',
        ],
        // 更多的磁盘配置信息
    ],
];
