<?php
declare(strict_types=1);

namespace Zxin\Redis\Model;

class TypeTransformManage
{
    /**
     * @var TypeTransform[]
     */
     private static $transforms = [];

    /**
     * @param TypeTransform $transform
     */
     public static function add(TypeTransform $transform)
     {
         self::$transforms[$transform->getName()] = $transform;
     }

    /**
     * @param string $name
     * @return bool
     */
     public static function has(string $name): bool
     {
         return isset(self::$transforms[$name]);
     }

    /**
     * @param string $name
     * @return TypeTransform|null
     */
     public static function get(string $name):? TypeTransform
     {
         return self::$transforms[$name] ?? null;
     }

    /**
     * @param string $name
     * @return void
     */
     public static function del(string $name): void
     {
         unset(self::$transforms[$name]);
     }
}
