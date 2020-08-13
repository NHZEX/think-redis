<?php
declare(strict_types=1);

namespace Zxin\Think\Redis;

class Service extends \think\Service
{
    public function register()
    {
        $this->app->bind('redis', RedisManager::class);
    }

    public function boot()
    {
    }
}
