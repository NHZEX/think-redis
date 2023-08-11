<?php

declare(strict_types=1);

namespace Zxin\Redis\Lua;

use InvalidArgumentException;
use Zxin\Redis\Connections\PhpRedisConnection;
use Zxin\Redis\Exception\RedisLuaException;
use function array_merge;
use function is_null;
use function sha1;
use function str_starts_with;
use function count;

abstract class RedisLua
{
    protected ?string $name = null;

    /** @var string[] */
    protected static array $luaSha1;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name ?? static::class;
    }

    public function getCode(): string
    {
        return $this->luaCode();
    }

    public function getSha1(): string
    {
        $name = $this->getName();
        return self::$luaSha1[$name] ?? (self::$luaSha1[$name] = sha1($this->luaCode()));
    }

    /**
     * @return bool
     */
    public function loaded(PhpRedisConnection $redis): bool
    {
        return $redis->script('exists', $this->getSha1())[0] > 0;
    }

    public function load(PhpRedisConnection $redis): void
    {
        if (!$this->loaded($redis)) {
            $redis->clearLastError();
            $result = $redis->script('load', $this->luaCode());
            if (false === $result) {
                throw new RedisLuaException($redis->getLastError());
            }
            if ($this->getSha1() !== $result) {
                throw new RedisLuaException('load lua fail');
            }
        }
    }

    /**
     * @return mixed
     */
    protected function invoke(PhpRedisConnection $redis, array $keys, array $argv = [])
    {
        if (count($keys) !== $this->numKeys()) {
            throw new InvalidArgumentException('Keys length error.');
        }

        $isRetry = false;
        RETRY_EVAL:
        $redis->clearLastError();
        $result = $redis->evalSha($this->getSha1(), array_merge($keys, $argv), $this->numKeys());
        if (false === $result
            && !is_null($error = $redis->getLastError())
            && str_starts_with($error, 'NOSCRIPT')
        ) {
            if (false === $isRetry) {
                $isRetry = true;
                $this->load($redis);
                goto RETRY_EVAL;
            } else {
                throw new RedisLuaException($redis->getLastError());
            }
        }

        return $result;
    }

    abstract protected function numKeys(): int;

    abstract protected function luaCode(): string;
}
