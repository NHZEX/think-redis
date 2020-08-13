<?php

namespace Zxin\Redis;

use InvalidArgumentException;
use think\Config;
use Zxin\Redis\Connections\PhpRedisConnection;

/**
 * Class RedisProvider
 * @package Zxin\Redis
 */
class RedisProvider
{
    /** @var array 配置 */
    protected $config = [];

    /**
     * The Redis connections.
     *
     * @var PhpRedisConnection[]
     */
    protected $connections;

    public function __construct(Config $config)
    {
        $this->config = $config->get('redis') + $this->config;
    }

    /**
     * @param null $name
     * @return PhpRedisConnection
     */
    public function connection($name = null)
    {
        $default = $name ?? $this->config['default'];
        if (!isset($this->config['connections'][$default])) {
            throw new InvalidArgumentException("invalid connection: {$default}");
        }

        if (!isset($this->connections[$default])) {
            $this->connections[$default] = new PhpRedisConnection($this->config['connections'][$default]);
        }

        return $this->connections[$default];
    }

    public function destroy($name = null)
    {
        $default = $name ?? $this->config['default'];
        if (!isset($this->config['connections'][$default])) {
            throw new InvalidArgumentException("invalid connection: {$default}");
        }

        unset($this->connections[$default]);
    }
}
