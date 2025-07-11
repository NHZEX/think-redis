<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Php74\Rector\LNumber\AddLiteralSeparatorToNumberRector;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/think',
    ]);

    $rectorConfig->skip([
        JsonThrowOnErrorRector::class,
        AddLiteralSeparatorToNumberRector::class,
    ]);

    $rectorConfig->rules([]);

    // define sets of rules
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_80,
    ]);

    $rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');
};
