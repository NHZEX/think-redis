<?php
declare(strict_types=1);

namespace Zxin\Tests;

use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;
use Zxin\Think\Redis\Pool\PoolConnector;
use Zxin\Think\Redis\RedisManager;

#[RequiresPhp('>= 8.1')]
#[RequiresPhpExtension('swoole', '>= 5.0')]
class SwooleEnvTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (Coroutine::getCid() === -1) {
            self::markTestSkipped('Coroutine is not available.');
        }
    }
    public function testManager()
    {
        $wg = new Coroutine\WaitGroup();
        $wg->add();

        Coroutine::create(function () use ($wg) {
            Coroutine::defer(fn () => $wg->done());

            $redis1 = RedisManager::connection('data');
            $id1    = spl_object_hash($redis1);

            RedisManager::destroy('data');
            $redis2 = RedisManager::connection('data');
            $id2    = spl_object_hash($redis2);

            $this->assertNotEquals($id1, $id2);
        });

        $wg->wait();
    }

    public function testConnection()
    {
        $config = RedisManager::getInstance()->getConnectionsConfig('data');
        $poolConfig = PoolConnector::pullPoolConfig($config);
//        var_dump($poolConfig);
        $poolMaxActive = $poolConfig['maxActive'];
        $redis = RedisManager::store('data');
        $baseConnectedClients = $redis->info()['connected_clients'];

        $wg = new Coroutine\WaitGroup();
        $wg->add();
        Coroutine::create(function () use ($wg, $baseConnectedClients, $poolMaxActive) {
            Coroutine::defer(fn () => $wg->done());
            $limit = 2000;
            $maxClients = 0;
            $failCount = 0;
            $wg2 = new Coroutine\WaitGroup();
            for ($i = 0; $i < $limit; $i++) {
                $wg2->add();
                Coroutine::create(function () use ($wg2, $i, &$maxClients, &$failCount) {
                    Coroutine::defer(fn () => $wg2->done());
                    #echo sprintf('> [C] %d - %d', $i, Coroutine::getCid()), PHP_EOL;
                    $redis = RedisManager::store('data');
                    $info = $redis->info();
                    #echo sprintf('> [D] %d - %d', $i, Coroutine::getCid()), PHP_EOL;
                    if (empty($info)) {
                        $failCount++;
                    }
                    $maxClients = max($maxClients, $info['connected_clients']);
                });
            }
            $wg2->wait();
            self::assertEquals(0, $failCount);
            self::assertGreaterThanOrEqual($poolMaxActive, $maxClients);
            self::assertLessThanOrEqual($poolMaxActive + 3, $maxClients);
        });

        $wg->wait();
    }
}
