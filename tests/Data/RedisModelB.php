<?php
declare(strict_types=1);

namespace Zxin\Tests\Data;

use Zxin\Redis\RedisModel;

/**
 * Class RedisModelA
 * @package Zxin\Tests\Data
 * @property int    $intVal
 * @property float  $floatVal
 * @property bool   $boolVal
 * @property string $strVal
 * @property array  $arrVal
 */
class RedisModelB extends RedisModel
{
    protected $integrityCheck = true;
    protected $metadataCheck = true;

    protected $type = [
        'intVal'   => 'int',
        'floatVal' => 'float',
        'boolVal'  => 'bool',
        'strVal'   => 'string',
        'arrVal'   => 'msgpack',
    ];
}
