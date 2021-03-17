<?php

declare(strict_types=1);

namespace Zxin\Think\Redis\Pool;

use ArrayObject;
use Swoole\Coroutine;

class Context
{
    /**
     * 获取协程上下文
     * @param int $cid
     * @return Coroutine\Context
     */
    public static function get($cid = 0)
    {
        return Coroutine::getContext($cid);
    }

    public static function getDataObject()
    {
        $context = static::get();
        if (!isset($context[static::class])) {
            $context[static::class] = new ArrayObject();
        }
        return $context[static::class];
    }

    public static function getData(string $key, $default = null)
    {
        if (static::hasData($key)) {
            return static::getDataObject()->offsetGet($key);
        }
        return $default;
    }

    public static function hasData(string $key)
    {
        return static::getDataObject()->offsetExists($key);
    }

    public static function setData(string $key, $value)
    {
        static::getDataObject()->offsetSet($key, $value);
    }

    public static function removeData(string $key)
    {
        if (static::hasData($key)) {
            static::getDataObject()->offsetUnset($key);
        }
    }

    /**
     * @param string $key
     * @param callable $value
     * @return mixed|null
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

    public static function clear()
    {
        static::getDataObject()->exchangeArray([]);
    }
}
