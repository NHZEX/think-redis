<?php

declare(strict_types=1);

namespace Zxin\Redis\Model;

use Zxin\Redis\Connections\PhpRedisConnection;
use Zxin\Redis\Lua\RedisLua;

class LuaHMSetIntegrity extends RedisLua
{
    public static function eval(PhpRedisConnection $redis, string $key, array $array, bool $merge, int $ttl = 0): bool
    {
        $argv[] = $ttl;
        $argv[] = $merge;
        foreach ($array as $index => $value) {
            $argv[] = $index;
            $argv[] = $value;
        }
        $result = (new self())->invoke($redis, [$key], $argv);
        return $result == 1;
    }

    protected function numKeys(): int
    {
        return 1;
    }

    protected function luaCode(): string
    {
        return <<<LUA
            local hashKey = KEYS[1]
            local ttl = tonumber(ARGV[1])
            local merge = tonumber(ARGV[2])
            table.remove(ARGV, 1)
            table.remove(ARGV, 1)

            if merge == 1 and redis.call('EXISTS', hashKey) == 1 then
              for i=1,table.getn(ARGV),2 do
                redis.call('hSet', hashKey, ARGV[i], ARGV[i+1])
              end
            else
              redis.call('hMSet', hashKey, unpack(ARGV))
            end

            local keys = redis.call('hKeys', hashKey)
            for i=table.getn(keys),1,-1 do
              if keys[i] == '__integrity' or keys[i] == '__metaCheck' then
                table.remove(keys, i)
              end
            end
            table.sort(keys)
            local keysStr = table.concat(keys, '.')
            redis.call('hSet', hashKey, '__integrity', redis.sha1hex(keysStr))

            if ttl > 0 then
              redis.call('expire', hashKey, ttl)
            end
            return true
            LUA;
    }
}
