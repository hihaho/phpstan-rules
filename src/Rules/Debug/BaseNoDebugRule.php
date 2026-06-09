<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use PhpParser\Node\Expr\MethodCall;
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
    protected const array FUNCTION_DEBUG_STATEMENTS = [
        'dump' => true,
        'dd' => true,
        'ddd' => true,
        'ray' => true,
        'print_r' => true,
        'var_dump' => true,
    ];

    /** @var array<string, true> */
    protected const array METHOD_DEBUG_STATEMENTS = [
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

    /**
     * A `->dump()` / `->dd()` chain is only a real debug call when the method
     * is declared by a Laravel-framework class or trait. Unrelated user methods
     * that happen to share the name (e.g. a custom `->dump()` on a value object)
     * are not flagged. Unknown receiver types are skipped.
     */
    final protected function isDebugHelperMethodCall(MethodCall $node, Scope $scope, string $methodName): bool
    {
        $classReflections = $scope->getType($node->var)->getObjectClassReflections();

        if ($classReflections === []) {
            return false;
        }

        foreach ($classReflections as $classReflection) {
            if (! $classReflection->hasMethod($methodName)) {
                continue;
            }

            $declaringClassName = $classReflection->getMethod($methodName, $scope)->getDeclaringClass()->getName();

            if (str_starts_with($declaringClassName, self::LARAVEL_NAMESPACE_PREFIX)) {
                return true;
            }
        }

        return false;
    }
}
