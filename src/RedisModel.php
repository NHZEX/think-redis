<?php
declare(strict_types=1);

namespace Zxin\Redis;

use OutOfBoundsException;
use Zxin\Redis\Connections\PhpRedisConnection;
use Zxin\Redis\Exception\RedisModelException;
use Zxin\Redis\Model\LuaHMSetIntegrity;
use Zxin\Redis\Model\LuaSumIntegrity;
use Zxin\Redis\Model\LuaVerifyIntegrity;
use Zxin\Redis\Model\TypeTransformManage;
use function array_keys;
use function json_decode;
use function json_encode;
use function max;
use function serialize;

class RedisModel
{
    protected const KEY_INTEGRITY = '__integrity';

    /**
     * @var PhpRedisConnection
     */
    private $redis;
    /**
     * 未生效
     * @var string
     */
    private $connection;
    /**
     * @var string
     */
    private $table;
    /**
     * 原始数据
     * @var array
     */
    private $origin = [];
    /**
     * 数据信息
     * @var array
     */
    private $data = [];
    /**
     * 数据表字段信息
     * @var array
     */
    protected $field = [];
    /**
     * 类型转换
     * @var array
     */
    protected $type = [
    ];
    /**
     * 字段别名映射
     * @var array
     */
    protected $alias = [
    ];

    /** @var int */
    protected $defaultTTL = 180;

    /** @var bool 一致性 */
    protected $flagConcurrency = false;
    /** @var bool 完整性 */
    protected $flagIntegrity = false;

    /** @var bool */
    private $lazy;
    /** @var bool */
    private $exist = false;
    /** @var bool */
    private $valid = false;
    /** @var int */
    private $unsafeOperationCount = 0;

    public function __construct(string $name, PhpRedisConnection $redis, bool $lazy = false)
    {
        $this->table = $name;
        $this->lazy = $lazy;
        $this->redis = $redis;
    }

    public function load()
    {
        if (!LuaVerifyIntegrity::eval($this->redis, $this->table)) {
            return;
        }

        if ($this->lazy) {
            $this->exist = true;
            return;
        }

        $data = $this->redis->hGetAll($this->table);
        if (!empty($data)) {
            $this->origin = $data;
        }

        $this->field = empty($this->field) ? array_keys($this->origin) : $this->field;

        foreach ($this->field as $field) {
            if (isset($this->origin[$field])) {
                $this->data[$field] = $this->origin[$field];
            }
        }
    }

    public function save(int $ttl = 0)
    {
        $ttl = max($ttl ?: $this->defaultTTL, 0);
        if (LuaHMSetIntegrity::eval($this->redis, $this->table, $this->data, $this->lazy, $ttl)) {
            $this->origin = $this->data;
            return true;
        };
        return false;
    }

    public function computingIntegrity(): bool
    {
        $this->unsafeOperationCount = 0;
        return LuaSumIntegrity::eval($this->redis, $this->table);
    }

    /**
     * todo
     * @return string
     */
    protected function buildConcurrencyHash(): string
    {
        return sha1(serialize($this->type));
    }

    public function refreshTTL(int $ttl)
    {
        $this->redis->expire($this->table, $ttl);
    }

    public function destroy()
    {
        $this->redis->del($this->table);
    }

    public function isExist()
    {
        return $this->redis->isTypeHash($this->table);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        if ($this->lazy) {
            return !$this->exist;
        } else {
            return empty($this->data);
        }
    }

    /**
     * @param $name
     * @return int|bool|string|array
     */
    public function getData(?string $name)
    {
        if ($name === null) {
            return $this->data;
        }
        $rawName = $name;
        $name    = $this->alias[$name] ?? $name;

        if ($this->lazy && !isset($this->data[$name])) {
            $data = $this->redis->hGet($this->table, $name);
            if ($data !== false) {
                $this->data[$name] = $data;
            }
        }

        if (!isset($this->data[$name])) {
            throw new OutOfBoundsException("property not exists: {$name}($rawName)");
        }
        $result = $this->data[$name];

        return isset($this->type[$name]) ? $this->readTransform($result, $this->type[$name]) : $result;
    }

    /**
     * @param string                $name
     * @param int|bool|string|array $value
     */
    public function setData(string $name, $value): void
    {
        $name              = $this->alias[$name] ?? $name;
        $value             = isset($this->type[$name]) ? $this->writeTransform($value, $this->type[$name]) : $value;
        $this->data[$name] = $value;
    }

    protected function readTransform(string $value, string $type)
    {
        switch ($type) {
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
                if ($value === '0') {
                    return false;
                } elseif ($value === '1') {
                    return true;
                }
                return (bool) $value;
            case 'json':
                return json_decode($value, true);
            default:
                if ($transform = TypeTransformManage::get($type)) {
                    return $transform->readTransform($value);
                }
                return $value;
        }
    }

    protected function writeTransform($value, string $type): string
    {
        switch ($type) {
            case 'bool':
                return $value ? '1' : '0';
            case 'json':
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            default:
                if ($transform = TypeTransformManage::get($type)) {
                    return $transform->writeTransform($value);
                }
                return (string) $value;
        }
    }

    /**
     * @param $name
     * @param $value
     * @return void
     */
    public function __set(string $name, $value)
    {
        $this->setData($name, $value);
    }

    /**
     * @param string $name
     * @return array|bool|int|string
     */
    public function __get(string $name)
    {
        return $this->getData($name);
    }

    /**
     * @param string $name
     * @return boolean
     */
    public function __isset(string $name): bool
    {
        $name = $this->alias[$name] ?? $name;

        if ($this->lazy) {
            return isset($this->data[$name]) || $this->redis->hExists($this->table, $name);
        }

        return isset($this->data[$name]);
    }

    /**
     * @param string $name
     * @return void
     */
    public function __unset(string $name)
    {
        unset($this->data[$name]);

        if ($this->lazy) {
            $this->unsafeOperationCount++;
            $this->redis->hDel($this->table, $name);
        }
    }

    public function __destruct()
    {
        if ($this->unsafeOperationCount > 0) {
            throw new RedisModelException("There are unsaved operations ({$this->table})");
        }
    }
}
