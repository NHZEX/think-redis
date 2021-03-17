<?php

declare(strict_types=1);

namespace Zxin\Redis\Model;

use Zxin\Redis\Connections\PhpRedisConnection;
use Zxin\Redis\Lua\RedisLua;

class LuaSumIntegrity extends RedisLua
{
    public static function eval(PhpRedisConnection $redis, string $key): bool
    {
        return (new self())->invoke($redis, [$key]) == 1;
    }

    protected function numKeys(): int
    {
        return 1;
    }

    protected function luaCode(): string
    {
        /**
         * $keys = array_keys($data);
         * asort($keys, SORT_STRING);
         * return sha1(join('.', $keys));
         */
        return <<<LUA
local hashKey = KEYS[1]

if redis.call('EXISTS', hashKey) == 0 then
  return false
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
return true
LUA;
    }
}
