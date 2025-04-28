<?php

declare(strict_types=1);

namespace Zxin\Think\Redis\Pool;

use Redis;

class RedisPipeline
{
    public function __construct(private RedisConnections $connections, private Redis $redis, private Redis $chain)
    {
    }

    /**
     * @return RedisPipeline|mixed
     */
    public function __call(string $method, array $arguments)
    {
        try {
            $result = $this->chain->{$method}(...$arguments);
            if ($this->connections->__isFastFreed()) {
                State::migrate($method, $this->redis);
            }
            if ($result instanceof Redis) {
                $result = $this;
            }
        } finally {
            if ($this->connections->__isFastFreed()) {
                $this->connections->__return();
            }
        }
        return $result;
    }
}
