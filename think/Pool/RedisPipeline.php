<?php
declare(strict_types=1);

namespace Zxin\Think\Redis\Pool;

use Redis;

class RedisPipeline
{
    /**
     * @var RedisConnections
     */
    private $connections;
    /**
     * @var Redis
     */
    private $redis;

    /**
     * @var Redis
     */
    private $chain;

    public function __construct(RedisConnections $connections, Redis $redis, Redis $chain)
    {
        $this->connections = $connections;
        $this->redis = $redis;
        $this->chain = $chain;
    }

    public function __call($method, $arguments)
    {
        try {
            $result = $this->chain->{$method}(...$arguments);
            $this->connections->__isFastFreed() && State::migrate($method, $this->redis);
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
