<?php
declare(strict_types=1);

namespace Zxin\Tests\Data;

use Zxin\Redis\RedisModel;

/**
 * Class RedisModelA
 * @package Zxin\Tests\Data
 * @property int    $intVal
 * @property float  $floatVal
 * @property bool   $trueVal
 * @property bool   $falseVal
 * @property string $strVal
 * @property array  $arrVal
 */
class RedisModelA extends RedisModel
{
    protected $integrityCheck = true;

    protected $type = [
        'intVal'   => 'int',
        'floatVal' => 'float',
        'trueVal'  => 'bool',
        'falseVal'  => 'bool',
        'strVal'   => 'string',
        'arrVal'   => 'json',
    ];
}
