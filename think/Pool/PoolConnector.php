<?php
declare(strict_types=1);

namespace Zxin\Think\Redis\Pool;

use Redis;
use Smf\ConnectionPool\Connectors\ConnectorInterface;
use think\helper\Arr;
use function call_user_func;
use function spl_object_id;

class PoolConnector implements ConnectorInterface
{
    /** @var callable */
    protected $creator;

    /** @var array */
    protected $objRecord = [];

    public static function pullPoolConfig(&$config)
    {
        return [
            'minActive'         => Arr::pull($config, 'pool.min_active', 0),
            'maxActive'         => Arr::pull($config, 'pool.max_active', 10),
            'maxWaitTime'       => Arr::pull($config, 'pool.max_wait_time', 5),
            'maxIdleTime'       => Arr::pull($config, 'pool.max_idle_time', 30),
            'idleCheckInterval' => Arr::pull($config, 'pool.idle_check_interval', 60),
        ];
    }

    public function __construct(callable $creator)
    {
        $this->creator = $creator;
    }

    public function connect(array $config)
    {
        /**@var Redis $connection */
        $connection = call_user_func($this->creator, $config);
        State::init($connection);

        $this->objRecord[spl_object_id($connection)] = true;
        return $connection;
    }

    public function disconnect($connection)
    {
        /**@var Redis $connection */
        $connection->close();
        unset($this->objRecord[spl_object_id($connection)]);
    }

    public function isConnected($connection): bool
    {
        /**@var Redis $connection */
        return $connection->isConnected();
    }

    public function reset($connection, array $config)
    {
    }

    public function validate($connection): bool
    {
        return $connection instanceof Redis && isset($this->objRecord[spl_object_id($connection)]);
    }
}
