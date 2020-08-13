<?php
declare(strict_types=1);

namespace Zxin\Tests\Model;

use PHPUnit\Framework\TestCase;
use Zxin\Tests\Data\RedisModelA;
use Zxin\Think\Redis\RedisManager;

class RedisModelTest extends TestCase
{
    public function testModel()
    {
        $key = 'test:' . __METHOD__;

        $redis = RedisManager::connection();
        $model = new RedisModelA($key, $redis);
        $model->intVal = 123456;
        $model->floatVal = 1.123456789;
        $model->boolVal = true;
        $model->strVal = 'qwe,asd,zxc';
        $model->arrVal = [1, 2, 3, 4, 5];
        $model->save();

        $this->assertEquals([
            'intVal' => '123456',
            'floatVal' => '1.123456789',
            'boolVal' => '1',
            'strVal' => 'qwe,asd,zxc',
            'arrVal' => '[1,2,3,4,5]',
            '__integrity' => '50480b4d4cc56563281a90313aedc35eb5fe6fd0',
        ], $redis->hGetAll($key));

        $model = new RedisModelA($key, $redis);
        $model->load();
        $this->assertTrue($model->intVal === 123456);
        $this->assertTrue($model->floatVal === 1.123456789);
        $this->assertTrue($model->boolVal === true);
        $this->assertTrue($model->strVal === 'qwe,asd,zxc');
        $this->assertTrue($model->arrVal === [1, 2, 3, 4, 5]);

        $model->destroy();
    }

    public function testRobustness()
    {
        $key = 'test:' . __METHOD__;

        $redis = RedisManager::connection();
        $model = new RedisModelA($key, $redis);
        $model->intVal = 123456;
        $model->floatVal = 1.123456789;
        $model->boolVal = true;
        $model->strVal = 'qwe,asd,zxc';
        $model->arrVal = [1, 2, 3, 4, 5];
        $model->save();

        $redis->hDel($key, 'strVal');

        $model = new RedisModelA($key, $redis);
        $model->load();
        $this->assertTrue($model->isEmpty());
        $this->assertTrue($redis->exists($key) === 0);
    }
}
