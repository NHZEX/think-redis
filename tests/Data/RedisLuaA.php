<?php
declare(strict_types=1);

namespace Zxin\Tests\Data;

use Zxin\Redis\Connections\PhpRedisConnection;
use Zxin\Redis\Lua\RedisLua;

class RedisLuaA extends RedisLua
{

    protected function numKeys(): int
    {
        return 2;
    }

    protected function luaCode(): string
    {
        return <<<'LUA'
local expired = redis.call('HGET', KEYS[1], KEYS[2])
local result = false
if expired == false or ARGV[2] > expired then
    redis.call('HSET', KEYS[1], KEYS[2], ARGV[2] + ARGV[1])
    result = true
end
return result
LUA;
    }

    public function eval(PhpRedisConnection $redis,string $key, string $hashKey, int $lockTtl, int $time)
    {
        $result = $this->invoke($redis, [$key, $hashKey], [$lockTtl, $time]);
        return $result === 1;
    }
}
