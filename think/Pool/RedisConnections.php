<?php

declare(strict_types=1);

namespace Zxin\Think\Redis\Pool;

use Closure;
use Redis;
use RuntimeException;
use Smf\ConnectionPool\ConnectionPool;
use Swoole\Coroutine;
use think\App;
use Zxin\Redis\Connections\PhpRedisConnection;
use Zxin\Redis\Exception\RedisPoolException;
use function class_exists;
use function spl_object_id;
use function sprintf;

class RedisConnections extends PhpRedisConnection
{
    private static $swooleExist = false;

    /** @var string */
    private $poolName;

    /** @var bool */
    private $fastFreed = false;

    /** @var bool */
    private $autoDiscard = false;

    /** @var App */
    private $app;

    /** @var ConnectionPool */
    private $pool;

    /** @var bool */
    private $closed = false;

    public function __construct(array $config, App $app)
    {
        $this->__init();

        $this->app = $app;
        parent::__construct($config);
    }

    private function __init()
    {
        self::$swooleExist = class_exists(Coroutine::class);
        $this->poolName = 'connection.' . spl_object_id($this);
    }

    protected function __initPool()
    {
        if ($this->closed) {
            throw new RedisPoolException('pool is closed.');
        }
        $this->fastFreed = (bool) ($this->config['fast_freed'] ?? false);
        $this->autoDiscard = (bool) ($this->config['auto_discard'] ?? false);
        $this->pool = new ConnectionPool(
            PoolConnector::pullPoolConfig($this->config),
            new PoolConnector(Closure::fromCallable([$this, '__connection'])),
            $this->config
        );
        $this->pool->init();
    }

    public function __closePool()
    {
        if ($this->pool) {
            $this->closed = true;
            $this->pool->close();
            $this->pool = null;
        }
    }

    public function __pool(): ConnectionPool
    {
        return $this->pool;
    }

    protected function __poolName()
    {
        return $this->poolName;
    }

    /**
     * @return bool
     */
    public function __isFastFreed(): bool
    {
        return $this->fastFreed;
    }

    public function __borrow(): Redis
    {
        return Context::rememberData($this->__poolName(), function () {
            $connection = $this->pool->borrow();

            $connection->{State::KEY_RELEASED} = false;

            Coroutine::defer(function () {
                $this->__return(true);
            });

            return $connection;
        });
    }

    public function __return(bool $all = false)
    {
        if ($all) {
            /** @var Redis $connection */
            foreach (Context::getDataObject()->getIterator() as $key => $connection) {
                if ($key !== $this->__poolName()) {
                    continue;
                }
                if ($connection->{State::KEY_LOCK_TRANSACTION}) {
                    if ($this->autoDiscard) {
                        $connection->discard();
                        $connection->{State::KEY_LOCK_TRANSACTION} = false;
                    } else {
                        throw new RedisPoolException(sprintf(
                            'release obj#%d warning: uncommitted transaction',
                            spl_object_id($connection)
                        ));
                    }
                }
                if ($connection->{State::KEY_LOCK_WATCH}) {
                    if ($this->autoDiscard) {
                        $connection->unwatch();
                        $connection->{State::KEY_LOCK_WATCH} = false;
                    } else {
                        throw new RedisPoolException(sprintf(
                            'release obj#%d warning: unreleased watch',
                            spl_object_id($connection)
                        ));
                    }
                }
                $connection->{State::KEY_RELEASED} = true;
                Context::removeData($key);
                $this->pool->return($connection);
            }
        } else {
            /** @var Redis $connection */
            $connection = Context::getData($this->__poolName());
            if (empty($connection)) {
                return;
            }
            if ($connection->{State::KEY_RELEASED}) {
                return;
            }
            if ($connection->{State::KEY_LOCK_TRANSACTION} || $connection->{State::KEY_LOCK_WATCH}) {
                return;
            }
            $connection->{State::KEY_RELEASED} = true;
            Context::removeData($this->__poolName());
            $this->pool->return($connection);
        }
    }

    protected function __invokePool($method, array $arguments = [], bool $fastFreed = false)
    {
        $connection = $this->__borrow();
        if ($connection->{State::KEY_RELEASED}) {
            throw new RuntimeException("Connection already has been released!");
        }
        try {
            $result = $connection->{$method}(...$arguments);
            if ($result instanceof Redis) {
                $result = new RedisPipeline($this, $connection, $result);
            }
            $fastFreed && State::migrate($method, $connection);
        } finally {
            if ($fastFreed) {
                $this->__return();
            }
        }
        return $result;
    }

    public function __command($method, array $parameters = [])
    {
        if (!self::$swooleExist || Coroutine::getCid() === -1) {
            return parent::__command($method, $parameters);
        } else {
            if ($this->pool === null) {
                $this->__initPool();
            }
            return $this->__invokePool($method, $parameters, $this->fastFreed);
        }
    }

    public function __destruct()
    {
        $this->__closePool();
        parent::__destruct();
    }
}
