<?php
declare(strict_types=1);

namespace Zxin\Think\Redis\Pool;

use Smf\ConnectionPool\Connectors\ConnectorInterface;
use think\helper\Arr;
use function call_user_func;

class PoolConnector implements ConnectorInterface
{
    /** @var callable */
    protected $creator;

    public static function pullPoolConfig(&$config)
    {
        return [
            'minActive'         => Arr::pull($config, 'min_active', 0),
            'maxActive'         => Arr::pull($config, 'max_active', 10),
            'maxWaitTime'       => Arr::pull($config, 'max_wait_time', 5),
            'maxIdleTime'       => Arr::pull($config, 'max_idle_time', 20),
            'idleCheckInterval' => Arr::pull($config, 'idle_check_interval', 10),
        ];
    }

    public function __construct(callable $creator)
    {
        $this->creator = $creator;
    }

    public function connect(array $config)
    {
        return call_user_func($this->creator, $config);
    }

    public function disconnect($connection)
    {
    }

    public function isConnected($connection): bool
    {
        return true;
    }

    public function reset($connection, array $config)
    {
    }

    public function validate($connection): bool
    {
        return true;
    }
}
