<?php

declare(strict_types=1);

namespace Zxin\Think\Redis\Pool;

use Closure;
use Redis;
use RuntimeException;
use Smf\ConnectionPool\ConnectionPool;
use Swoole\Coroutine;
use Zxin\Redis\Connections\PhpRedisConnection;
use Zxin\Redis\Exception\RedisPoolException;
use function class_exists;
use function spl_object_id;
use function sprintf;

class RedisConnections extends PhpRedisConnection
{
    /** @var bool */
    private static bool|null $swooleExist = null;

    private string $poolName;

    private bool $fastFreed = false;

    private bool $autoDiscard = false;

    private ?ConnectionPool $pool = null;

    private bool $closed = false;

    public function __construct(array $config)
    {
        $this->__init();
        parent::__construct($config);
    }

    public static function enableSwooleSupport(bool $enable): void
    {
        if (self::$swooleExist === $enable) {
            return;
        }
        if (null !== self::$swooleExist) {
            throw new RuntimeException('This value can only be initialized once');
        }
        self::$swooleExist = $enable;
    }

    private function __init(): void
    {
        if (null === self::$swooleExist) {
            self::$swooleExist = class_exists(Coroutine::class);
        }
        $this->poolName = 'connection.' . spl_object_id($this);
    }

    protected function __initPool(): void
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

    public function __closePool(): void
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

    protected function __poolName(): string
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

            State::setValue(State::KEY_RELEASED, $connection, false);

            Coroutine::defer(function () {
                $this->__return(true);
            });

            return $connection;
        });
    }

    public function __return(bool $all = false): void
    {
        if (null === $this->pool) {
            return;
        }
        if ($all) {
            /** @var Redis $connection */
            foreach (Context::getDataObject()->getIterator() as $key => $connection) {
                if ($key !== $this->__poolName()) {
                    continue;
                }
                if (State::getValue(State::KEY_LOCK_TRANSACTION, $connection)) {
                    if ($this->autoDiscard) {
                        $connection->discard();
                        State::setValue(State::KEY_LOCK_TRANSACTION, $connection, false);
                    } else {
                        throw new RedisPoolException(sprintf(
                            'release obj#%d warning: uncommitted transaction',
                            spl_object_id($connection)
                        ));
                    }
                }
                if (State::getValue(State::KEY_LOCK_WATCH, $connection)) {
                    if ($this->autoDiscard) {
                        $connection->unwatch();
                        State::setValue(State::KEY_LOCK_WATCH, $connection, false);
                    } else {
                        throw new RedisPoolException(sprintf(
                            'release obj#%d warning: unreleased watch',
                            spl_object_id($connection)
                        ));
                    }
                }
                State::setValue(State::KEY_RELEASED, $connection, true);
                Context::removeData($key);
                $this->pool->return($connection);
            }
        } else {
            /** @var Redis|null $connection */
            $connection = Context::getData($this->__poolName());
            if (empty($connection)) {
                return;
            }
            if (State::getValue(State::KEY_RELEASED, $connection)) {
                return;
            }
            if (State::getValue(State::KEY_LOCK_TRANSACTION, $connection) || State::getValue(State::KEY_LOCK_WATCH, $connection)) {
                return;
            }
            State::setValue(State::KEY_RELEASED, $connection, true);
            Context::removeData($this->__poolName());
            $this->pool->return($connection);
        }
    }

    /**
     * @return RedisPipeline
     */
    protected function __invokePool(string $method, array $arguments = [], bool $fastFreed = false)
    {
        $connection = $this->__borrow();
        if (State::getValue(State::KEY_RELEASED, $connection)) {
            throw new RuntimeException("Connection already has been released!");
        }
        try {
            $result = $connection->{$method}(...$arguments);
            if ($result instanceof Redis) {
                $result = new RedisPipeline($this, $connection, $result);
            }
            if ($fastFreed) {
                State::migrate($method, $connection);
            }
        } finally {
            if ($fastFreed) {
                $this->__return();
            }
        }
        return $result;
    }

    /**
     * @param string $method
     * @param array  $parameters
     * @return mixed|RedisPipeline
     */
    public function __command(string $method, array $parameters = [])
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
