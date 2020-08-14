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
        return <<<'LUA'
local hashKey = KEYS[1]
local metaHash = ARGV[1]

if redis.call('EXISTS', hashKey) == 0 then
  return false
end

if metaHash ~= '0' and redis.call('hGet', hashKey, '__metaCheck') ~= metaHash then
  redis.call('del', hashKey)
  return false
end
local integrity = redis.call('hGet', hashKey, '__integrity')
if integrity == false then
  return false
end

local keys = redis.call('hKeys', hashKey)
for k, v in pairs(keys) do
  if v == '__integrity' or v == '__metaCheck' then
    table.remove(keys, k)
  end
end
table.sort(keys)
local keysStr = table.concat(keys, '.')

if redis.sha1hex(keysStr) ~= integrity then
  redis.call('del', hashKey)
  return false
end

return true
LUA;
    }
}
