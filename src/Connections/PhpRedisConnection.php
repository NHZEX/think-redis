<?php

declare(strict_types=1);

namespace Zxin\Redis\Connections;

use Redis;
use RedisException;
use Zxin\Redis\RedisExtend;
use function array_merge;
use function version_compare;
use function phpversion;

/**
 * Class PhpRedisConnection
 * @package app\Service\Redis\Connections
 * @mixin RedisExtend
 */
class PhpRedisConnection
{
    /** @var RedisExtend|null */
    private $client;

    /** @var array<string, string|int|bool|array|null> */
    protected $config = [
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password'   => '',
        'select'     => 0,
        'timeout'    => 1,
        'persistent' => false,
        'prefix'     => null,
        'options'    => [],
    ];

    /**
     * Create a new PhpRedis connection.
     *
     * @param array<string, string|int|bool|array> $config
     */
    public function __construct(array $config)
    {
        $this->config = array_merge($this->config, $config);
    }

    public function getConnectionConfig(): array
    {
        return $this->config;
    }

    protected function __connection(array $config): RedisExtend
    {
        $client     = new RedisExtend();
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
            foreach ($config['options'] ?? [] as $key => $value) {
                $client->setOption($key, $value);
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
    public function __command(string $method, array $parameters = [])
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
    public function __call(string $method, array $parameters)
    {
        return $this->__command($method, $parameters);
    }

    /**
     * @param int|null    $iterator
     * @param string|null $pattern
     * @param int         $count
     * @return array|false
     * @see \Redis::scan
     */
    public function scan(?int &$iterator, ?string $pattern = null, int $count = 0)
    {
        return $this->__command(__FUNCTION__, [&$iterator, $pattern, $count]);
    }

    /**
     * @param string      $key
     * @param int|null    $iterator
     * @param string|null $pattern
     * @param int         $count
     * @return array|false
     * @see \Redis::sScan
     */
    public function sScan(string $key, ?int &$iterator, ?string $pattern = null, int $count = 0)
    {
        return $this->__command(__FUNCTION__, [$key, &$iterator, $pattern, $count]);
    }

    /**
     * @param string      $key
     * @param int|null    $iterator
     * @param string|null $pattern
     * @param int         $count
     * @return array
     * @see \Redis::hScan
     */
    public function hScan(string $key, ?int &$iterator, ?string $pattern = null, int $count = 0)
    {
        return $this->__command(__FUNCTION__, [$key, &$iterator, $pattern, $count]);
    }

    /**
     * @param string      $key
     * @param int|null    $iterator
     * @param string|null $pattern
     * @param int         $count
     * @return array|false
     * @see \Redis::zScan
     */
    public function zScan(string $key, ?int &$iterator, ?string $pattern = null, int $count = 0)
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
