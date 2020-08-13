<?php

require_once __DIR__ . '/../vendor/autoload.php';

use think\App;
use Zxin\Think\Redis\Service;

$config = require __DIR__ . '/../config/redis.php';

$app = new App(__DIR__);
$app->register(Service::class);
$app->config->set([
    'default'         => 'file',
    'stores'  => [
        'file' => [
            'type'       => 'File',
            'path'       => '/tmp/cache',
        ],
    ],
], 'cache');
$app->config->set($config, 'redis');
$app->console;