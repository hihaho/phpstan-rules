<?php declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\CodeQuality\Rector\If_\ExplicitNullableObjectInstanceRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php80\Rector\Ternary\TernaryToNullsafeCoalesceRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\AddSeeTestAnnotationRector;
use Rector\Privatization\Rector\ClassMethod\PrivatizeFinalClassMethodRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;

// Rector rejects a containerCacheDirectory that does not exist yet.
is_dir(__DIR__ . '/.cache/phpstan') || mkdir(__DIR__ . '/.cache/phpstan', recursive: true);

return RectorConfig::configure()
    ->withCache(
        cacheDirectory: './.cache/rector',
        // Shares PHPStan's tmpDir so the reflection cache is stored once.
        containerCacheDirectory: './.cache/phpstan',
    )
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/tests/Rules/stubs',
        __DIR__ . '/tests/Rules/Debug/stubs',
        __DIR__ . '/tests/Rules/Validation/stubs',
        __DIR__ . '/tests/Rules/Conventions/stubs',
        __DIR__ . '/tests/Rules/Livewire/stubs',
        __DIR__ . '/tests/Presets/stubs',
        __DIR__ . '/tests/Rules/Exceptions/stubs',
        __DIR__ . '/tests/Rules/Database/stubs',
        __DIR__ . '/tests/ReturnTypes/stubs',
        __DIR__ . '/tests/ParameterClosureTypes/stubs',
        AddArrowFunctionReturnTypeRector::class,
        // Rector writes the FQCN, Pint's fully_qualified_strict_types shortens it back, and the next
        // Rector run appends a duplicate — the two tools never converge on a @see annotation.
        AddSeeTestAnnotationRector::class,
        // Optional third-party classes (livewire/livewire) are not dependencies.
        StringClassNameToClassConstantRector::class => [
            __DIR__ . '/src/Rules/Livewire',
        ],
        InlineArrayReturnAssignRector::class,
        PrivatizeFinalClassMethodRector::class,
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        // Its `$x?->prop ?? $d` output fails PHPStan's nullsafe.neverNull.
        TernaryToNullsafeCoalesceRector::class,
        // On a nullable enum, its rewrite lets the deadCode set delete reachable code.
        ExplicitNullableObjectInstanceRector::class,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
    )
    ->withComposerBased(phpunit: true)
    ->withAttributesSets()
    ->withImportNames()
    ->withEditorUrl('phpstorm://open?file=%file%&line=%line%')
    ->withParallel(300, 15, 15)
    ->withMemoryLimit('3G')
    ->withPhpSets(php84: true);
