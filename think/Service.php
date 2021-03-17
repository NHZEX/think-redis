<?php

declare(strict_types=1);

namespace Zxin\Think\Redis;

class Service extends \think\Service
{
    public function register(): void
    {
        $this->app->bind('redis', RedisManager::class);
    }
}
