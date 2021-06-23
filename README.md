# Think-Redis

[![Latest Stable Version](https://poser.pugx.org/zxin/think-redis/v/stable)](https://packagist.org/packages/zxin/think-redis)
[![License](https://poser.pugx.org/zxin/think-redis/license)](https://packagist.org/packages/zxin/think-redis)
[![workflows](https://github.com/nhzex/think-redis/workflows/ci/badge.svg)](https://github.com/NHZEX/think-redis/actions)
[![coverage](https://codecov.io/gh/nhzex/think-redis/graph/badge.svg)](https://codecov.io/gh/nhzex/think-redis)

## 快速使用

1. 安装
```bash
composer require zxin/think-redis
```

1. 普通环境下直接使用。
```php
\Zxin\Think\Redis\RedisManager::store()->get('xxxx');
```

2. 协程环境下使用方法与普通环境一致，但为了更优雅的退出，在`workerStop`与`workerExit`下调用以下代码。  
```php
\Zxin\Think\Redis\RedisManager::destroy();
```