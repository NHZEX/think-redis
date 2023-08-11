<?php

declare(strict_types=1);

namespace Zxin\Redis\Model;

use Zxin\Redis\Connections\PhpRedisConnection;
use Zxin\Redis\Lua\RedisLua;

class LuaHashModelCheck extends RedisLua
{
    public static function eval(PhpRedisConnection $redis, string $key, ?string $metaCheck): bool
    {
        return (new self())->invoke($redis, [$key], [$metaCheck ?? 0]) == 1;
    }

    protected function numKeys(): int
    {
        return 1;
    }

    protected function luaCode(): string
    {
        // 0: exists
        // 1: success
        // 2: {['err'] = 'metadata check fail'}
        // 3: {['err'] = 'integrity does not exist'}
        // 4: {['err'] = 'integrity check fail: ' .. sumHash}
        return <<<'LUA'
            local hashKey = KEYS[1]
            local metaHash = ARGV[1]

            if redis.call('EXISTS', hashKey) == 0 then
              return 0
            end

            if metaHash ~= '0' and redis.call('hGet', hashKey, '__metaCheck') ~= metaHash then
              redis.call('del', hashKey)
              return 2
            end
            local integrity = redis.call('hGet', hashKey, '__integrity')
            if integrity == false then
              return 3
            end

            local keys = redis.call('hKeys', hashKey)
            for i=table.getn(keys),1,-1 do
              if keys[i] == '__integrity' or keys[i] == '__metaCheck' then
                table.remove(keys, i)
              end
            end
            table.sort(keys)
            local keysStr = table.concat(keys, '.')
            local sumHash = redis.sha1hex(keysStr)

            if sumHash ~= integrity then
              redis.call('del', hashKey)
              return 4
            end

            return 1
            LUA;
    }
}
