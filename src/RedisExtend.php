<?php

namespace Zxin\Redis;

use Error;
use Redis;
use UnexpectedValueException;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * Class RedisExtend
 * @package app\Service\Redis
 * TODO 处理 evalSha 错误问题
 * @method bool isTypeString(string $name)
 * @method bool isTypeSet(string $name)
 * @method bool isTypeList(string $name)
 * @method bool isTypeZset(string $name)
 * @method bool isTypeHash(string $name)
 * @method bool isTypeStream(string $name)
 */
class RedisExtend extends Redis
{
    private $type = [
        Redis::REDIS_NOT_FOUND => 'null',
        Redis::REDIS_STRING => 'string',
        Redis::REDIS_SET => 'set',
        Redis::REDIS_LIST => 'list',
        Redis::REDIS_ZSET => 'zset',
        Redis::REDIS_HASH => 'hash',
        Redis::REDIS_STREAM => 'stream'
    ];

    public function getServerVersion() :string
    {
        /** @var array $redis_info */
        $redis_info = $this->info('SERVER');
        return $redis_info['redis_version'];
    }

    public function __call($name, $arguments)
    {
        if (str_starts_with($name, 'isType')) {
            $type = $this->type($arguments[0]);
            if (!isset($this->type[$type])) {
                throw new UnexpectedValueException("Unknown redis type: {$type}");
            }
            return $this->type[$type] === strtolower(substr($name, 6));
        }
        throw new Error(sprintf('Call to undefined method %s::%s()', static::class, $name));
    }
}
