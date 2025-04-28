<?php

namespace Zxin\Think\Redis;

use think\cache\Driver;
use think\exception\InvalidCacheException;
use Zxin\Redis\Connections\PhpRedisConnection;
use DateInterval;
use function array_merge;
use function is_null;

class CacheDriver extends Driver
{
    /**
     * 驱动句柄
     * @var PhpRedisConnection
     */
    protected $handler = null;

    /**
     * 配置参数
     * @var array
     */
    protected $options = [
        'connection' => null,
        'expire'     => 0,
        'prefix'     => '',
        'tag_prefix' => 'tag:',
        'serialize'  => [],
    ];

    public function __construct(array $options = [])
    {
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        if (empty($this->options['connection'])) {
            $this->options['connection'] = null;
        }
        $this->handler = RedisManager::connection($this->options['connection']);
    }

    public function inc($name, $step = 1)
    {
        $this->writeTimes++;

        $key = $this->getCacheKey($name);

        return $this->handler->incrby($key, $step);
    }

    public function dec($name, $step = 1)
    {
        $this->writeTimes++;

        $key = $this->getCacheKey($name);

        return $this->handler->decrby($key, $step);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->readTimes++;

        $cKey   = $this->getCacheKey($key);
        $value = $this->handler()->get($cKey);

        if (false === $value || is_null($value)) {
            return $this->getDefaultValue($key, $default);
        }

        try {
            return $this->unserialize($value);
        } catch (InvalidCacheException) {
            return $this->getDefaultValue($key, $default, true);

        }
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->writeTimes++;

        if (is_null($ttl)) {
            $ttl = $this->options['expire'];
        }

        $key    = $this->getCacheKey($key);
        $expire = $this->getExpireTime($ttl);
        $value  = $this->serialize($value);

        if ($expire) {
            $this->handler->setex($key, $expire, $value);
        } else {
            $this->handler->set($key, $value);
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $this->writeTimes++;

        /** @var int|false $result */
        $result = $this->handler->del($this->getCacheKey($key));
        return $result > 0;
    }

    public function has($key): bool
    {
        return (bool) $this->handler->exists($this->getCacheKey($key));
    }

    /**
     * 清除缓存
     */
    public function clear(): bool
    {
        $this->writeTimes++;

        $this->handler->flushDB();
        return true;
    }

    /**
     * 追加（数组）缓存数据
     * @param string $name  缓存标识
     * @param mixed  $value 数据
     * @return void
     */
    public function push($name, $value): void
    {
        $this->handler->sAdd($name, $value);
    }

    /**
     * 删除缓存标签
     */
    public function clearTag($keys): void
    {
        // 指定标签清除
        $this->handler->del($keys);
    }

    /**
     * 追加TagSet数据
     */
    public function append($name, $value): void
    {
        $key = $this->getCacheKey($name);
        $this->handler()->sAdd($key, $value);
    }

    /**
     * 获取标签包含的缓存标识
     * @param string $tag 缓存标签
     * @return array
     */
    public function getTagItems(string $tag): array
    {
        return $this->handler->sMembers($tag);
    }
}
