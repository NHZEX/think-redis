<?php
declare(strict_types=1);

namespace Zxin\Think\Redis\Pool;

use Closure;
use RuntimeException;
use Smf\ConnectionPool\ConnectionPool;
use Swoole\Coroutine;
use think\App;
use think\swoole\coroutine\Context;
use Zxin\Redis\Connections\PhpRedisConnection;
use function class_exists;
use function spl_object_id;

class RedisConnections extends PhpRedisConnection
{
    private static $swooleExist = false;

    const KEY_RELEASED = '__released';

    /** @var App */
    private $app;

    /** @var ConnectionPool */
    private $pool;

    public function __construct(array $config, App $app)
    {
        self::$swooleExist = class_exists(Coroutine::class);

        $this->app = $app;
        parent::__construct($config);
    }

    protected function __initPool()
    {
        $this->pool = new ConnectionPool(
            PoolConnector::pullPoolConfig($this->config),
            new PoolConnector(Closure::fromCallable([$this, '__connection'])),
            $this->config
        );
        $this->pool->init();
    }

    protected function __poolName()
    {
        return 'connection.' . spl_object_id($this);
    }

    protected function __getPoolConnection()
    {
        return Context::rememberData($this->__poolName(), function () {
            $connection = $this->pool->borrow();

            $connection->{static::KEY_RELEASED} = false;

            Coroutine::defer(function () use ($connection) {
                //自动归还
                $connection->{static::KEY_RELEASED} = true;
                $this->pool->return($connection);
            });

            return $connection;
        });
    }

    protected function __invokePool($method, array $arguments = [])
    {
        $connection = $this->__getPoolConnection();
        if ($connection->{static::KEY_RELEASED}) {
            throw new RuntimeException("Connection already has been released!");
        }

        return $connection->{$method}(...$arguments);
    }

    public function __command($method, array $parameters = [])
    {
        if (!self::$swooleExist || Coroutine::getCid() === -1) {
            return parent::__command($method, $parameters);
        } else {
            if ($this->pool === null) {
                $this->__initPool();
            }
            return $this->__invokePool($method, $parameters);
        }
    }
}
