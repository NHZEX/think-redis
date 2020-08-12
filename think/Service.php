<?php
declare(strict_types=1);

namespace Zxin\Think\Redis;

use Zxin\Redis\RedisProvider;

class Service extends \think\Service
{
    public function register()
    {
        $this->app->bind('redis', RedisProvider::class);
    }

    public function boot()
    {
    }
}
