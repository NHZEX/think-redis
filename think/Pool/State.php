<?php

declare(strict_types=1);

namespace Zxin\Think\Redis\Pool;

use Redis;
use WeakMap;
use stdClass;
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
    /**
     * @var WeakMap<Redis, stdClass>
     */
    private static WeakMap $map;

    public static function init(Redis $connection): void
    {
        if (!isset(self::$map)) {
            self::$map = new WeakMap();
        }

        $state = new stdClass();
        $state->{self::KEY_LOCK_TRANSACTION} = false;
        $state->{self::KEY_LOCK_WATCH} = false;
        self::$map[$connection] = $state;
    }

    public static function migrate(string $method, Redis $connection): void
    {
        if (!isset(self::$map[$connection])) {
            return;
        }
        $state = self::$map[$connection];

        $method = strtolower($method);
        switch ($method) {
            case self::M_T_MULTI:
                $state->{self::KEY_LOCK_TRANSACTION} = true;
                break;
            case self::M_T_DISCARD:
                $state->{self::KEY_LOCK_TRANSACTION} = false;
                break;
            case self::M_T_EXEC:
                $state->{self::KEY_LOCK_TRANSACTION} = false;
                $state->{self::KEY_LOCK_WATCH} = false;
                break;
            case self::M_T_WATCH:
                $state->{self::KEY_LOCK_WATCH} = true;
                break;
            case self::M_T_UNWATCH:
                $state->{self::KEY_LOCK_WATCH} = false;
                break;
        }
    }

    public static function getValue(string $method, Redis $connection): mixed
    {
        if (!isset(self::$map[$connection])) {
            return null;
        }
        return self::$map[$connection]->{$method};
    }

    public static function setValue(string $method, Redis $connection, mixed $value): void
    {
        if (!isset(self::$map[$connection])) {
            return;
        }
        self::$map[$connection]->{$method} = $value;
    }
}
