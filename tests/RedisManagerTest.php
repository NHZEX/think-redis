<?php
declare(strict_types=1);

namespace Zxin\Tests;

use PHPUnit\Framework\TestCase;
use think\helper\Str;
use Zxin\Tests\Data\RedisLuaA;
use Zxin\Think\Redis\RedisManager;
use function spl_object_hash;

class RedisManagerTest extends TestCase
{
    public function testManager()
    {
        $redis1 = RedisManager::connection('data');
        $id1    = spl_object_hash($redis1);

        RedisManager::destroy('data');
        $redis2 = RedisManager::connection('data');
        $id2    = spl_object_hash($redis2);

        $this->assertNotEquals($id1, $id2);
    }

    public function testConnection()
    {
        $redis = RedisManager::connection();
        $result = $redis->ping();
        $this->assertTrue($result === true || $result === '+PONG');

        $version = $redis->getServerVersion();
        $this->assertIsString($version);
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

    public function scanKeyDataProvider(string $key, $count = 10)
    {
        $randArr = [];
        for ($i = $count; $i--; ) {
            $randArr[sprintf('%s-%d', $key, $i)] = Str::random(8);
        }
        ksort($randArr);
        return $randArr;
    }

    public function testScan()
    {
        $redis = RedisManager::connection();

        $randArr = $this->scanKeyDataProvider(__FUNCTION__, 10);

        foreach ($randArr as $key => $item) {
            $redis->set($key, $item);
        }

        $limit = 100;
        $keys = [];
        $it = null;
        do {
            $keys = array_merge($keys, $redis->scan($it, sprintf('%s-*', __FUNCTION__), 2));
        } while ($it !== 0 && $limit--);

        $testData = [];
        foreach ($keys as $key) {
            $testData[$key] = $redis->get($key);
            $redis->del($key);
        }
        ksort($testData);

        $this->assertEquals($randArr, $testData);
    }

    public function testHScan()
    {
        $key = 'test:' . __METHOD__;

        $redis = RedisManager::connection();

        $randArr = $this->scanKeyDataProvider(__FUNCTION__, 10);

        $redis->hMSet($key, $randArr);

        $limit = 100;
        $hashData = [];
        $it = null;
        do {
            $hashData = array_merge($hashData, $redis->hScan($key, $it, sprintf('%s-*', __FUNCTION__), 2));
        } while ($it !== 0 && $limit--);

        $redis->del($key);
        ksort($hashData);

        $this->assertEquals($randArr, $hashData);
    }

    public function testSScan()
    {
        $key = 'test:' . __METHOD__;

        $redis = RedisManager::connection();

        $randArr = $this->scanKeyDataProvider(__FUNCTION__, 10);
        $randArr = array_keys($randArr);
        asort($randArr);

        $redis->sAddArray($key, $randArr);

        $limit = 100;
        $setData = [];
        $it = null;
        do {
            $setData = array_merge($setData, $redis->sScan($key, $it, sprintf('%s-*', __FUNCTION__), 2));
        } while ($it !== 0 && $limit--);
        asort($setData, SORT_STRING);
        $setData = array_merge($setData, []);
        $redis->del($key);

        $this->assertEquals($randArr, $setData);
    }

    public function testZScan()
    {
        $key = 'test:' . __METHOD__;

        $redis = RedisManager::connection();

        $randArr = $this->scanKeyDataProvider(__FUNCTION__, 10);
        $randArr = array_keys($randArr);
        asort($randArr);

        foreach ($randArr as $i => $value) {
            $redis->zAdd($key, $i, $value);
        }

        $limit = 100;
        $zData = [];
        $it = null;
        do {
            $zData = array_merge($zData, $redis->zScan($key, $it, sprintf('%s-*', __FUNCTION__), 2));
        } while ($it !== 0 && $limit--);
        $zData = array_map(function ($value) {
            return (int) $value;
        }, $zData);
        $zData = array_flip($zData);
        asort($zData, SORT_STRING);
        $redis->del($key);

        $this->assertEquals($randArr, $zData);
    }
}
