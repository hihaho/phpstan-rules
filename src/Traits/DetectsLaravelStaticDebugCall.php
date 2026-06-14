<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Traits;

use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;

/**
 * Shared detection for a `Class::dump()` / `Class::dd()` call that is a real
 * Laravel debug helper — used by both the standalone
 * StaticChainedNoDebugInNamespaceRule and the registered CombinedStaticCallRule.
 *
 * A static debug method is a genuine Laravel call when either:
 *   1. PHPStan resolves the method and its declaring class lives in the Laravel
 *      namespace (facades that expose `dump`/`dd` via `@method`, e.g. `Http::dump()`), OR
 *   2. The class is a subclass of `Illuminate\Support\Facades\Facade` — facades
 *      without a `@method` annotation still proxy through `Facade::__callStatic`
 *      to `Dumpable`/`EnumeratesValues` at runtime.
 *
 * The `$facadeReflection` is supplied by the caller (resolved once in its
 * constructor) so the readonly consumers keep their cached field and this trait
 * stays free of mutable state.
 */
trait DetectsLaravelStaticDebugCall
{
    private const string LARAVEL_DEBUG_NAMESPACE_PREFIX = 'Illuminate\\';

    private function isLaravelStaticDebugCall(
        StaticCall $node,
        Scope $scope,
        string $methodName,
        ReflectionProvider $reflectionProvider,
        ?ClassReflection $facadeReflection,
    ): bool {
        if (! $node->class instanceof Name) {
            return false;
        }

        $className = $scope->resolveName($node->class);

        if (! $reflectionProvider->hasClass($className)) {
            return false;
        }

        $classReflection = $reflectionProvider->getClass($className);

        if ($classReflection->hasMethod($methodName)) {
            $declaringClassName = $classReflection->getMethod($methodName, $scope)->getDeclaringClass()->getName();

            if (str_starts_with($declaringClassName, self::LARAVEL_DEBUG_NAMESPACE_PREFIX)) {
                return true;
            }
        }

        if (! $facadeReflection instanceof ClassReflection) {
            return false;
        }

        return $classReflection->isSubclassOfClass($facadeReflection);
    }
}
