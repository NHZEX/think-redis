<?php
declare(strict_types=1);

namespace Zxin\Tests;

use PHPUnit\Framework\TestCase;
use think\App;
use Zxin\Think\Redis\RedisManager;
use Zxin\Think\Redis\Service;
use function serialize;

class ThinkTest extends TestCase
{
    public function testTpService()
    {
        $app = App::getInstance();
        $app->register(Service::class);

        $this->assertTrue($app->has('redis'));
    }

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
        $this->assertTrue($cache->delete($key));
    }
}
