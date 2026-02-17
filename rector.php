<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/bootstrap',
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/resources',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php84: true) // Update to 8.5 when officially supported by sets, 8.3/8.4 as baseline
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::PRIVATIZATION,
        SetList::NAMING,
        SetList::TYPE_DECLARATION,
        SetList::INSTANCEOF,
        SetList::EARLY_RETURN,
    ])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
    ]);