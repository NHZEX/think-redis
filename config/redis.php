<?php

return [
    // 默认使用的数据库连接配置
    'default'     => 'data',
    // 数据库连接配置信息
    'connections' => [
        'data' => [
            'host'       => '127.0.0.1',
            'port'       => 6379,
            'password'   => '',
            'select'     => 0,
            'timeout'    => 3,
            'persistent' => false,
        ],
    ],
];
