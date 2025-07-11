<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPhpVersion(\Rector\ValueObject\PhpVersion::PHP_84)
    ->withPaths([
        __DIR__ . '/action',
        __DIR__ . '/api',
        __DIR__ . '/connecteur',
        __DIR__ . '/connecteur-type',
        __DIR__ . '/controler',
        __DIR__ . '/lib',
        __DIR__ . '/mailsec',
        __DIR__ . '/model',
        __DIR__ . '/module',
        __DIR__ . '/pastell-core',
        __DIR__ . '/src',
        __DIR__ . '/test',
        __DIR__ . '/tests',
        __DIR__ . '/type-dossier',
    ])
    ->withRules([
        \Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector::class,
        \Rector\Php84\Rector\FuncCall\AddEscapeArgumentRector::class,
    ])
;
