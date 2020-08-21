<?php

namespace Zxin\Redis\Model;

use MessagePack\MessagePack;

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
