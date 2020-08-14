<?php
declare(strict_types=1);

namespace Zxin\Tests\Model;

use PHPUnit\Framework\TestCase;
use Zxin\Redis\Exception\RedisModelException;
use Zxin\Redis\Model\TypeTransformManage;
use Zxin\Tests\Data\MsgpackType;
use Zxin\Tests\Data\RedisModelA;
use Zxin\Tests\Data\RedisModelACopy;
use Zxin\Tests\Data\RedisModelB;
use Zxin\Think\Redis\RedisManager;

class RedisModelTest extends TestCase
{
    public function testBasic()
    {
        $key = 'test:' . __METHOD__;

        $redis = RedisManager::connection();
        $model = new RedisModelA($key, $redis);
        $this->assertFalse(isset($model->intVal));
        $model->intVal = 123456;
        $this->assertTrue(isset($model->intVal));
        unset($model->intVal);
        $this->assertFalse(isset($model->intVal));
    }

    public function testModel()
    {
        $key = 'test:' . __METHOD__;

        $redis = RedisManager::connection();
        $model = new RedisModelA($key, $redis);
        $model->intVal = 123456;
        $model->floatVal = 1.123456789;
        $model->trueVal = true;
        $model->falseVal = false;
        $model->strVal = 'qwe,asd,zxc';
        $model->arrVal = [1, 2, 3, 4, 5];
        $this->assertTrue($model->save(666));

        $this->assertTrue($model->isExist());

        $this->assertTrue($redis->ttl($key) > 660);
        $model->refreshTTL(888);
        $this->assertTrue($redis->ttl($key) > 880);

        $this->assertEquals([
            'intVal' => '123456',
            'floatVal' => '1.123456789',
            'trueVal' => '1',
            'falseVal' => '0',
            'strVal' => 'qwe,asd,zxc',
            'arrVal' => '[1,2,3,4,5]',
            '__integrity' => '8c5ec53ae747933f116e7d0bc0f0a7d4ff055c8b',
        ], $redis->hGetAll($key));

        $model = new RedisModelA($key, $redis);
        $model->load();
        $this->assertTrue($model->intVal === 123456);
        $this->assertTrue($model->floatVal === 1.123456789);
        $this->assertTrue($model->trueVal);
        $this->assertFalse($model->falseVal);
        $this->assertTrue($model->strVal === 'qwe,asd,zxc');
        $this->assertTrue($model->arrVal === [1, 2, 3, 4, 5]);

        $model->destroy();
    }

    public function testLazyModel()
    {
        $key = 'test:' . __METHOD__;

        $redis = RedisManager::connection();
        $model = new RedisModelA($key, $redis, true);
        $model->intVal = 123456;
        $model->floatVal = 1.123456789;
        $model->trueVal = true;
        $model->falseVal = false;
        $model->strVal = 'qwe,asd,zxc';
        $model->arrVal = [1, 2, 3, 4, 5];
        $this->assertTrue($model->save());

        $this->assertEquals([
            'intVal' => '123456',
            'floatVal' => '1.123456789',
            'trueVal' => '1',
            'falseVal' => '0',
            'strVal' => 'qwe,asd,zxc',
            'arrVal' => '[1,2,3,4,5]',
            '__integrity' => '8c5ec53ae747933f116e7d0bc0f0a7d4ff055c8b',
        ], $redis->hGetAll($key));

        $model = new RedisModelA($key, $redis, true);
        $this->assertTrue(isset($model->strVal));
        $model->load();
        $this->assertFalse($model->isEmpty());
        $this->assertEquals('qwe,asd,zxc', $model->strVal);
        $model->strVal = 'asd,dfg,hjk';
        $model->save();
        $this->assertEquals('8c5ec53ae747933f116e7d0bc0f0a7d4ff055c8b', $model->getData('__integrity'));

        $this->assertEquals([
            'intVal' => '123456',
            'floatVal' => '1.123456789',
            'trueVal' => '1',
            'falseVal' => '0',
            'strVal' => 'asd,dfg,hjk',
            'arrVal' => '[1,2,3,4,5]',
            '__integrity' => '8c5ec53ae747933f116e7d0bc0f0a7d4ff055c8b',
        ], $redis->hGetAll($key));

        $model = new RedisModelA($key, $redis, true);
        $this->assertTrue(isset($model->strVal));
        $model->load();
        $this->assertFalse($model->isEmpty());
        $this->assertEquals('asd,dfg,hjk', $model->strVal);

        $model->destroy();
    }

    public function testIntegrity1()
    {
        $key = 'test:' . __METHOD__;

        $redis = RedisManager::connection();
        $model = new RedisModelA($key, $redis);
        $model->intVal = 123456;
        $model->floatVal = 1.123456789;
        $model->trueVal = true;
        $model->strVal = 'qwe,asd,zxc';
        $model->arrVal = [1, 2, 3, 4, 5];
        $this->assertTrue($model->save());

        $model = new RedisModelA($key, $redis);
        $model->load();
        $this->assertFalse($model->isEmpty());
        $this->assertTrue($redis->exists($key) > 0);

        $redis->hDel($key, 'strVal');

        $model = new RedisModelA($key, $redis);
        $model->load();
        $this->assertTrue($model->isEmpty());
        $this->assertTrue($redis->exists($key) === 0);
    }

    public function testIntegrity2()
    {
        $key = 'test:' . __METHOD__;

        $redis = RedisManager::connection();
        $model = new RedisModelA($key, $redis);
        $model->intVal = 123456;
        $model->floatVal = 1.123456789;
        $model->trueVal = true;
        $model->strVal = 'qwe,asd,zxc';
        $model->arrVal = [1, 2, 3, 4, 5];
        $this->assertTrue($model->save());

        $model = new RedisModelA($key, $redis);
        $model->load();
        $this->assertFalse($model->isEmpty());
        $this->assertTrue($redis->exists($key) > 0);

        $redis->hDel($key, 'strVal');

        $model = new RedisModelA($key, $redis);
        $model->computingIntegrity();
        $model->load();
        $this->assertFalse($model->isEmpty());
        $this->assertTrue($redis->exists($key) > 0);

        $model->destroy();
    }

    public function testIntegrityLazy3()
    {
        $this->expectException(RedisModelException::class);
        $this->expectExceptionMessageMatches('/There are unsaved operations/');

        $key = 'test:' . __METHOD__;

        $redis = RedisManager::connection();
        $model = new RedisModelA($key, $redis, true);
        $model->intVal = 123456;
        $model->floatVal = 1.123456789;
        $model->trueVal = true;
        $model->falseVal = false;
        $model->strVal = 'qwe,asd,zxc';
        $model->arrVal = [1, 2, 3, 4, 5];
        $this->assertTrue($model->save());

        $this->assertEquals([
            'intVal' => '123456',
            'floatVal' => '1.123456789',
            'trueVal' => '1',
            'falseVal' => '0',
            'strVal' => 'qwe,asd,zxc',
            'arrVal' => '[1,2,3,4,5]',
            '__integrity' => '8c5ec53ae747933f116e7d0bc0f0a7d4ff055c8b',
        ], $redis->hGetAll($key));

        $modelErr = new RedisModelA($key, $redis, true);
        $modelErr->load();
        $this->assertFalse($modelErr->isEmpty());
        unset($modelErr->strVal);

        $model = new RedisModelA($key, $redis, true);
        $model->load();
        $this->assertTrue($model->isEmpty());

        unset($modelErr);
    }

    public function testModelMetadataCheck()
    {
        $key = 'test:' . __METHOD__;

        $redis = RedisManager::connection();
        $model = new RedisModelA($key, $redis, true);
        $model->intVal = 123456;
        $model->floatVal = 1.123456789;
        $model->trueVal = true;
        $model->falseVal = false;
        $model->strVal = 'qwe,asd,zxc';
        $model->arrVal = [1, 2, 3, 4, 5];
        $this->assertTrue($model->save());

        $this->assertTrue($redis->exists($key) > 0);

        $model = new RedisModelA($key, $redis, true);
        $model->load();
        $this->assertFalse($model->isEmpty());

        $this->assertTrue($redis->exists($key) > 0);

        $model = new RedisModelACopy($key, $redis, true);
        $model->load();
        $this->assertTrue($model->isEmpty());

        $this->assertTrue($redis->exists($key) === 0);
    }

    public function testTypeTransform()
    {
        $key = 'test:' . __METHOD__;

        TypeTransformManage::add(new MsgpackType());

        $data = [0, 1, 2, 8, 'd' => 4, 5, 6, 7, 8];

        $redis = RedisManager::connection();
        $model = new RedisModelB($key, $redis);
        $model->arrVal = $data;

        $this->assertEquals($data, $model->arrVal);
    }
}
