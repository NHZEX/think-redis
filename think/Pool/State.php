<?php
declare(strict_types=1);

namespace Zxin\Think\Redis\Pool;

use Redis;
use function strtolower;

class State
{
    const KEY_LOCK_TRANSACTION = '__lock_transaction';
    const KEY_LOCK_WATCH = '__lock_watch';
    const KEY_LOCK_SCAN = '__lock_scan';

    const M_T_MULTI   = 'multi';
    const M_T_EXEC    = 'exec';
    const M_T_DISCARD = 'discard';
    const M_T_WATCH   = 'watch';
    const M_T_UNWATCH = 'unwatch';

    public static function init(Redis $connection)
    {
        $connection->{self::KEY_LOCK_TRANSACTION} = false;
        $connection->{self::KEY_LOCK_WATCH} = false;
    }

    public static function migrate(string $method, Redis $connection)
    {
        $method = strtolower($method);
        switch ($method) {
            case self::M_T_MULTI:
                $connection->{self::KEY_LOCK_TRANSACTION} = true;
                break;
            case self::M_T_DISCARD:
                $connection->{self::KEY_LOCK_TRANSACTION} = false;
                break;
            case self::M_T_EXEC:
                $connection->{self::KEY_LOCK_TRANSACTION} = false;
                $connection->{self::KEY_LOCK_WATCH} = false;
                break;
            case self::M_T_WATCH:
                $connection->{self::KEY_LOCK_WATCH} = true;
                break;
            case self::M_T_UNWATCH:
                $connection->{self::KEY_LOCK_WATCH} = false;
                break;
        }
    }
}
