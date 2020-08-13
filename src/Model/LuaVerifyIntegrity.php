<?php
declare(strict_types=1);

namespace Zxin\Redis\Model;

use Zxin\Redis\Connections\PhpRedisConnection;
use Zxin\Redis\Lua\RedisLua;

class LuaVerifyIntegrity extends RedisLua
{
    public function eval(PhpRedisConnection $redis, string $key)
    {
        return $this->invoke($redis, [$key]) == 1;
    }

    protected function numKeys(): int
    {
        return 1;
    }

    protected function luaCode(): string
    {
        return <<<'LUA'
local hashKey = KEYS[1]

if redis.call('EXISTS', hashKey) == false then
  return false
end

local integrity = redis.call('hGet', hashKey, '__integrity')
if integrity == false then
  return false
end

local keys = redis.call('hKeys', hashKey)
for k, v in pairs(keys) do
  if v == '__integrity' then
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
