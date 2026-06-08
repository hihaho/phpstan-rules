<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * @template T of \PhpParser\Node
 * @implements Rule<T>
 */
abstract readonly class BaseNoDebugRule implements Rule
{
    use ChecksNamespace;

    /** @var array<string, true> */
    private const array FUNCTION_DEBUG_STATEMENTS = [
        'dump' => true,
        'dd' => true,
        'ddd' => true,
        'ray' => true,
        'print_r' => true,
        'var_dump' => true,
    ];

    /** @var array<string, true> */
    private const array METHOD_DEBUG_STATEMENTS = [
        'dump' => true,
        'dd' => true,
        'ddd' => true,
        'ray' => true,
    ];

    /**
     * Namespace prefix that identifies a class/trait as a Laravel-provided
     * debug helper declaration. Used to narrow chained method-call matches so
     * unrelated user methods sharing a name (e.g. a custom `->dump()`) are not
     * flagged.
     */
    protected const string LARAVEL_NAMESPACE_PREFIX = 'Illuminate\\';

    final protected function isDebugFunction(string $statement): bool
    {
        return isset(self::FUNCTION_DEBUG_STATEMENTS[$statement]);
    }

    final protected function isDebugMethod(string $statement): bool
    {
        return isset(self::METHOD_DEBUG_STATEMENTS[$statement]);
    }

    final protected function matchDebugNamespace(Scope $scope): ?string
    {
        if ($this->namespaceStartsWith($scope, 'App')) {
            return 'App';
        }

        if ($this->namespaceStartsWith($scope, 'Tests')) {
            return 'Tests';
        }

        return null;
    }
}
