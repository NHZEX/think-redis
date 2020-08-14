<?php
declare(strict_types=1);

namespace Zxin\Tests\Data;

/**
 * Class RedisModelA
 * @package Zxin\Tests\Data
 * @property int    $intVal
 * @property float  $floatVal
 * @property bool   $boolVal
 * @property string $strVal
 * @property array  $arrVal
 */
class RedisModelACopy extends RedisModelA
{
    protected $integrityCheck = true;
    protected $metadataCheck = true;

    protected $type = [
        'intVal'   => 'int',
        'floatVal' => 'float',
        'trueVal'  => 'bool',
        'falseVal'  => 'bool',
        'strVal'   => 'int', // 被修改
        'arrVal'   => 'json',
    ];
}
