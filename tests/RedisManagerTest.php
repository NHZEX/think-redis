<?php
declare(strict_types=1);

namespace Zxin\Tests;

use PHPUnit\Framework\TestCase;
use Zxin\Tests\Data\RedisLuaA;
use Zxin\Think\Redis\RedisManager;

class RedisManagerTest extends TestCase
{
    public function testConnection()
    {
        $redis = RedisManager::connection();
        /** @noinspection PhpParamsInspection */
        $result = $redis->ping();
        $this->assertTrue($result === true || $result ===  '+PONG');
    }

    public function testLuaEval()
    {
        $key = 'test:' . __METHOD__;

        $redis = RedisManager::connection();

        $lua = new RedisLuaA();

        $result = $lua->eval($redis, $key, 'test1', 60, 1596211200);
        $this->assertEquals(true, $result);
        $this->assertEquals((int) $redis->hGet($key, 'test1'), 1596211260);

        $result = $lua->eval($redis, $key, 'test1', 60, 1596211200);
        $this->assertEquals(false, $result);

        $redis->hSet($key, 'test1', 1596211199);
        $result = $lua->eval($redis, $key, 'test1', 60, 1596211200);
        $this->assertEquals(true, $result);

        $redis->del($key);
    }
}
