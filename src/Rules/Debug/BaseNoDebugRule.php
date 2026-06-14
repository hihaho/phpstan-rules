<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use Hihaho\PhpstanRules\Traits\DetectsLaravelStaticDebugCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @template T of \PhpParser\Node
 * @implements Rule<T>
 */
abstract readonly class BaseNoDebugRule implements Rule
{
    use ChecksNamespace;
    use DetectsLaravelStaticDebugCall;

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

    private const string FUNC_DEBUG_MESSAGE = 'No debug statements should be present in the %s namespace.';

    private const string CHAINED_DEBUG_MESSAGE = 'No chained debug statements should be present in the %s namespace.';

    private const string STATIC_DEBUG_MESSAGE = 'No statically called debug statements should be present in the %s namespace.';

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

    /**
     * Shared detection for a direct debug-function call (`dump(...)`,
     * `dd(...)`, `print_r(...)`, …) inside the App/Tests namespaces. Used by
     * both NoDebugInNamespaceRule and CombinedFuncCallRule.
     */
    final protected function funcDebugError(string $funcName, Scope $scope): ?IdentifierRuleError
    {
        if (! $this->isDebugFunction($funcName)) {
            return null;
        }

        $namespace = $this->matchDebugNamespace($scope);

        if ($namespace === null) {
            return null;
        }

        return RuleErrorBuilder::message(sprintf(self::FUNC_DEBUG_MESSAGE, $namespace))
            ->identifier("hihaho.debug.noDebugIn{$namespace}")
            ->build();
    }

    /**
     * Shared detection for a chained debug method call (`$x->dump()`) declared
     * by a Laravel class/trait. Used by both ChainedNoDebugInNamespaceRule and
     * CombinedMethodCallRule.
     */
    final protected function chainedDebugError(MethodCall $node, string $methodName, Scope $scope): ?IdentifierRuleError
    {
        if (! $this->isDebugMethod($methodName)) {
            return null;
        }

        $namespace = $this->matchDebugNamespace($scope);

        if ($namespace === null) {
            return null;
        }

        if (! $this->isDebugHelperMethodCall($node, $scope, $methodName)) {
            return null;
        }

        return RuleErrorBuilder::message(sprintf(self::CHAINED_DEBUG_MESSAGE, $namespace))
            ->identifier("hihaho.debug.noChainedDebugIn{$namespace}")
            ->build();
    }

    /**
     * Shared detection for a statically called debug method (`Cache::dump()`)
     * that proxies to a Laravel debug helper. Used by both
     * StaticChainedNoDebugInNamespaceRule and CombinedStaticCallRule. The
     * `$facadeReflection` is supplied by the caller (resolved once in its
     * constructor) so this readonly hierarchy keeps no mutable state.
     */
    final protected function staticDebugError(StaticCall $node, string $methodName, Scope $scope, ReflectionProvider $reflectionProvider, ?ClassReflection $facadeReflection): ?IdentifierRuleError
    {
        if (! $this->isDebugMethod($methodName)) {
            return null;
        }

        $namespace = $this->matchDebugNamespace($scope);

        if ($namespace === null) {
            return null;
        }

        if (! $this->isLaravelStaticDebugCall($node, $scope, $methodName, $reflectionProvider, $facadeReflection)) {
            return null;
        }

        return RuleErrorBuilder::message(sprintf(self::STATIC_DEBUG_MESSAGE, $namespace))
            ->identifier("hihaho.debug.noStaticChainedDebugIn{$namespace}")
            ->build();
    }
}
