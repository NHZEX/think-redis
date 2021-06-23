# Think-Redis

[![Latest Stable Version](https://poser.pugx.org/zxin/think-redis/v/stable)](https://packagist.org/packages/zxin/think-redis)
[![License](https://poser.pugx.org/zxin/think-redis/license)](https://packagist.org/packages/zxin/think-redis)
[![workflows](https://github.com/nhzex/think-redis/workflows/ci/badge.svg)](https://github.com/NHZEX/think-redis/actions)
[![coverage](https://codecov.io/gh/nhzex/think-redis/graph/badge.svg)](https://codecov.io/gh/nhzex/think-redis)

# 快速使用

### 安装
```bash
composer require zxin/think-redis
```

### 普通环境下直接使用。
```php
\Zxin\Think\Redis\RedisManager::store()->get('xxxx');
```

### 协程环境下使用方法与普通环境一致，但为了更优雅的退出，在`workerStop`与`workerExit`下调用以下代码。  
```php
\Zxin\Think\Redis\RedisManager::destroy();
```

### 替换`Cache`中的`redis`驱动以提供全局`redis`管理服务
1. 先在`config/redis.php`配置文件声明一个连接
2. `config/cache.php`配置文件中需要提供redis连接的位置替换驱动
```
[
    'default' => 'test',
    'stores' => [
        'test' => [
            'type' => \Zxin\Think\Redis\CacheDriver::class,
            'connection' => 'main_redis', // 在redis统一配置中声明的有效连接
            ...
        ],
        ...
    ]
]
```