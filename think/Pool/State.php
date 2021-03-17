<?php

declare(strict_types=1);

namespace Zxin\Think\Redis\Pool;

use Redis;
use function strtolower;

class State
{
    public const KEY_RELEASED = '__released';
    public const KEY_LOCK_TRANSACTION = '__lock_transaction';
    public const KEY_LOCK_WATCH = '__lock_watch';

    public const M_T_MULTI   = 'multi';
    public const M_T_EXEC    = 'exec';
    public const M_T_DISCARD = 'discard';
    public const M_T_WATCH   = 'watch';
    public const M_T_UNWATCH = 'unwatch';

    public static function init(Redis $connection): void
    {
        $connection->{self::KEY_LOCK_TRANSACTION} = false;
        $connection->{self::KEY_LOCK_WATCH} = false;
    }

    public static function migrate(string $method, Redis $connection): void
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
