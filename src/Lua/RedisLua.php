<?php
declare(strict_types=1);

namespace Zxin\Redis\Lua;

use InvalidArgumentException;
use RuntimeException;
use Zxin\Redis\Connections\PhpRedisConnection;
use function array_merge;
use function is_string;
use function sha1;
use function str_starts_with;

abstract class RedisLua
{
    /** @var string */
    private $name;

    /** @var string[] */
    protected static $luaSha1;

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
        if (isset(self::$luaSha1[$name])) {
            return self::$luaSha1[$name];
        }
        return self::$luaSha1[$name] = sha1($this->luaCode());
    }

    /**
     * @param PhpRedisConnection $redis
     * @return bool
     */
    public function loaded(PhpRedisConnection $redis): bool
    {
        return $redis->script('exists', $this->getSha1())[0] > 0;
    }

    /**
     * @param PhpRedisConnection $redis
     * @return bool
     */
    public function load(PhpRedisConnection $redis): bool
    {
        if (!$this->loaded($redis)) {
            return $this->getSha1() === $redis->script('load', $this->luaCode());
        }
        return true;
    }

    protected function invoke(PhpRedisConnection $redis, array $keys, array $argv = [])
    {
        if (count($keys) !== $this->numKeys()) {
            throw new InvalidArgumentException('Keys length error.');
        }

        $retry = 0;
        do {
            if ($retry > 0) {
                $this->load($redis);
            }
            $redis->clearLastError();
            $result = $redis->evalSha($this->getSha1(), array_merge($keys, $argv), $this->numKeys());
        } while (
            $result === false
            && is_string($error = $redis->getLastError())
            && str_starts_with($error, 'NOSCRIPT')
            && $retry++ === 0
        );

        if ($retry >= 2) {
            throw new RuntimeException($redis->getLastError());
        }

        return $result;
    }

    protected abstract function numKeys(): int;

    protected abstract function luaCode(): string;
}
