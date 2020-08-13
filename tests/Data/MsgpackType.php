<?php

namespace Zxin\Tests\Data;

use MessagePack\MessagePack;
use Zxin\Redis\Model\TypeTransform;

class MsgpackType extends TypeTransform
{
    protected $name = 'msgpack';

    public function readTransform(string $value)
    {
        return MessagePack::unpack($value);
    }

    public function writeTransform($value): string
    {
        return MessagePack::pack($value);
    }
}
