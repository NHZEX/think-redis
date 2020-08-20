<?php
declare(strict_types=1);

namespace Zxin\Redis\Connections;

use Redis;
use RedisException;
use Zxin\Redis\RedisExtend;
use function array_merge;

/**
 * Class PhpRedisConnection
 * @package app\Service\Redis\Connections
 * @mixin RedisExtend
 */
class PhpRedisConnection
{
    /** @var RedisExtend */
    private $client;

    protected $config = [
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password'   => '',
        'select'     => 0,
        'timeout'    => 1,
        'persistent' => false,
        'prefix'     => null,
    ];

    /**
     * Create a new PhpRedis connection.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * @return array
     */
    public function getConnectionConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     * @return RedisExtend
     */
    protected function __connection(array $config)
    {
        $client = new RedisExtend();
        $persistent = $config['persistent'] ?? false;

        $parameters = [
            $config['host'],
            $config['port'],
            $config['timeout'] ?? 0.0,
            $persistent ? ($config['persistent_id'] ?? null) : null,
            $config['retry_interval'] ?? 0,
        ];

        if (version_compare(phpversion('redis'), '3.1.3', '>=')) {
            $parameters[] = $config['read_timeout'] ?? 0.0;
        }

        try {
            if (false === $client->{($persistent ? 'pconnect' : 'connect')}(...$parameters)) {
                throw new RedisException('redis connect fail');
            }
            if (!empty($config['password'])) {
                if (false === $client->auth($config['password'])) {
                    throw new RedisException('redis auth fail');
                }
            }
            if (isset($config['select'])) {
                if (false === $client->select((int) $config['select'])) {
                    throw new RedisException('redis select fail');
                }
            }
            if (!empty($config['prefix'])) {
                $client->setOption(Redis::OPT_PREFIX, $config['prefix']);
            }
            if (!empty($config['read_timeout'])) {
                $client->setOption(Redis::OPT_READ_TIMEOUT, $config['read_timeout']);
            }
        } catch (RedisException $exception) {
            throw new \Zxin\Redis\Exception\RedisException(
                $exception->getMessage() . ', ' . $client->getLastError(),
                $exception->getCode(),
                $exception
            );
        }

        return $client;
    }

    /**
     * Run a command against the Redis database.
     *
     * @param string $method
     * @param array  $parameters
     * @return mixed
     */
    public function __command($method, array $parameters = [])
    {
        if (null === $this->client) {
            $this->client = $this->__connection($this->config);
        }
        return $this->client->{$method}(...$parameters);
    }

    /**
     * Pass other method calls down to the underlying client.
     *
     * @param string $method
     * @param array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->__command($method, $parameters);
    }

    /**
     * @param      $iterator
     * @param null $pattern
     * @param int  $count
     * @return array|bool
     * @see \Redis::scan
     */
    public function scan(&$iterator, $pattern = null, $count = 0)
    {
        return $this->__command(__FUNCTION__, [&$iterator, $pattern, $count]);
    }

    /**
     * @param      $key
     * @param      $iterator
     * @param null $pattern
     * @param int  $count
     * @return array|bool
     * @see \Redis::sScan
     */
    public function sScan($key, &$iterator, $pattern = null, $count = 0)
    {
        return $this->__command(__FUNCTION__, [$key, &$iterator, $pattern, $count]);
    }

    /**
     * @param      $key
     * @param      $iterator
     * @param null $pattern
     * @param int  $count
     * @return array
     * @see \Redis::hScan
     */
    public function hScan($key, &$iterator, $pattern = null, $count = 0)
    {
        return $this->__command(__FUNCTION__, [$key, &$iterator, $pattern, $count]);
    }

    /**
     * @param      $key
     * @param      $iterator
     * @param null $pattern
     * @param int  $count
     * @return array|bool
     * @see \Redis::zScan
     */
    public function zScan($key, &$iterator, $pattern = null, $count = 0)
    {
        return $this->__command(__FUNCTION__, [$key, &$iterator, $pattern, $count]);
    }

    public function __destruct()
    {
        if (null !== $this->client) {
            $this->client->close();
            $this->client = null;
        }
    }
}
