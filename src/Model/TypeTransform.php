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
    public abstract function readTransform(string $value);

    /**
     * @param mixed $value
     * @return string
     */
    public abstract function writeTransform($value): string;
}
