<?php

declare(strict_types=1);

namespace Zxin\Think\Redis;

use InvalidArgumentException;
use think\App;
use think\helper\Arr;
use think\Manager;
use Zxin\Redis\Connections\PhpRedisConnection;
use Zxin\Think\Redis\Pool\RedisConnections;
use function is_null;

class RedisManager extends Manager
{
    /**
     * @var RedisConnections[]
     */
    protected $drivers = [];

    public static function getInstance(): RedisManager
    {
        return App::getInstance()->make(static::class);
    }

    public static function hasInstance(): bool
    {
        return App::getInstance()->has(static::class);
    }

    /**
     * @param string|null $name
     * @return PhpRedisConnection
     */
    public static function store(?string $name = null)
    {
        return self::getInstance()->driver($name);
    }

    /**
     * @param string|null $name
     * @return PhpRedisConnection
     */
    public static function connection(?string $name = null)
    {
        return self::store($name);
    }

    /**
     * @param null $name
     * @return RedisManager
     */
    public static function destroy($name = null)
    {
        return self::getInstance()->forgetDriver($name);
    }

    public function getDefaultDriver()
    {
        return $this->getConfig('default');
    }

    /**
     * 获取配置
     * @access public
     * @param null|string $name    名称
     * @param mixed       $default 默认值
     * @return mixed
     */
    public function getConfig(?string $name = null, mixed $default = null)
    {
        if (!is_null($name)) {
            return $this->app->config->get('redis.' . $name, $default);
        }

        return $this->app->config->get('redis');
    }

    /**
     * 获取驱动配置
     * @param string|null $name
     * @param array|null  $default
     * @return array<string, mixed>|null
     */
    public function getConnectionsConfig(string $connections, ?string $name = null, $default = null): ?array
    {
        if ($config = $this->getConfig("connections.{$connections}")) {
            return Arr::get($config, $name, $default);
        }

        throw new InvalidArgumentException("Connections [$connections] not found.");
    }

    protected function resolveType(string $name)
    {
        return RedisConnections::class;
    }

    protected function resolveConfig(string $name)
    {
        return $this->getConnectionsConfig($name);
    }

    /**
     * 移除一个驱动实例
     *
     * @param array|string|null $name
     * @return $this
     */
    public function forgetDriver($name = null)
    {
        $name ??= $this->getDefaultDriver();

        foreach ((array) $name as $cacheName) {
            if (isset($this->drivers[$cacheName])) {
                $this->drivers[$cacheName]->__closePool();
                unset($this->drivers[$cacheName]);
            }
        }

        return $this;
    }
}
