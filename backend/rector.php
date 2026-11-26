<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withImportNames(removeUnusedImports: true)
    ->withSkip([
        RemoveUnusedPublicMethodParameterRector::class => [
            __DIR__.'/app/Listeners',
        ],
        ArrayToFirstClassCallableRector::class => [
            __DIR__.'/config',
            __DIR__.'/tests/Unit/Support/SentryEventScrubberTest.php',
        ],
        // Pest binds a dataset closure to the test case before calling it for a typed parameter, and a closure
        // made from a function (`systemAdmin(...)`) cannot be rebound: PHP 8.4 raises an error there.
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class => [
            __DIR__.'/tests/Feature/Platform/CsvExportStreamFailureTest.php',
        ],
    ]);
