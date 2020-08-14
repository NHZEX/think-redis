<?php
declare(strict_types=1);

namespace Zxin\Tests;

use PHPUnit\Framework\TestCase;
use think\App;
use Zxin\Think\Redis\RedisManager;
use function serialize;

class ThinkCacheTest extends TestCase
{
    public function testCache()
    {
        $key = 'test:' . __METHOD__;

        $cache = App::getInstance()->cache->store('redis');
        $redis = RedisManager::connection();

        $value = 'qwertyuiop';
        $cache->set($key, $value);

        $this->assertTrue($cache->has($key));

        $this->assertEquals(serialize($value), $redis->get($key));

        $this->assertEquals($value, $cache->get($key));
        $this->assertTrue($cache->delete($key));

        $this->assertEquals(1, $cache->inc($key));
        $this->assertEquals(0, $cache->dec($key));
    }
}
