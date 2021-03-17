<?php

declare(strict_types=1);

namespace Zxin\Redis\Model;

abstract class TypeTransform
{
    /** @var string */
    protected $name;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $value
     * @return mixed
     */
    abstract public function readTransform(string $value);

    /**
     * @param mixed $value
     * @return string
     */
    abstract public function writeTransform($value): string;
}
