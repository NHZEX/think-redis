<?php

declare(strict_types=1);

namespace Zxin\Think\Redis\Pool;

use ArrayObject;
use Swoole\Coroutine;

class Context
{
    /**
     * 获取协程上下文
     */
    public static function get(int $cid = 0): ?Coroutine\Context
    {
        return Coroutine::getContext($cid);
    }

    /**
     * @return ArrayObject<string|int, mixed>
     */
    public static function getDataObject(): ArrayObject
    {
        $context = static::get();
        if (!isset($context[static::class])) {
            $context[static::class] = new ArrayObject();
        }
        return $context[static::class];
    }

    public static function getData(string $key, mixed $default = null): mixed
    {
        if (static::hasData($key)) {
            return static::getDataObject()->offsetGet($key);
        }
        return $default;
    }

    public static function hasData(string $key): bool
    {
        return static::getDataObject()->offsetExists($key);
    }

    public static function setData(string $key, mixed $value): void
    {
        static::getDataObject()->offsetSet($key, $value);
    }

    public static function removeData(string $key): void
    {
        if (static::hasData($key)) {
            static::getDataObject()->offsetUnset($key);
        }
    }

    /**
     * @return mixed
     */
    public static function rememberData(string $key, callable $value)
    {
        if (static::hasData($key)) {
            return static::getData($key);
        }

        $result = $value();

        static::setData($key, $result);

        return $result;
    }

    public static function clear(): void
    {
        static::getDataObject()->exchangeArray([]);
    }
}
