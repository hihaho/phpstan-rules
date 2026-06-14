<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Traits;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\TypeCombinator;

/**
 * Detects a bare `true`/`false`/`null` literal passed positionally as the final
 * argument of a first-party method, static, or constructor call — where naming
 * the argument (`paramName: false`) would make the call self-documenting.
 *
 * The detection core (`flagSiteFor*`) returns a `{method, argIndex, paramName,
 * value}` record so it can drive both the error rules (CI gate) and the
 * named-argument manifest Collector (rector producer) from one implementation.
 *
 * Scope: the last argument only, and only when every argument is positional (no
 * named or spread args), so the arg index maps directly to the parameter index.
 * Any bare flag on a named, non-variadic parameter qualifies — the parameter is
 * NOT required to be bool-typed, matching the convention (a bare null on a
 * `?Object`/`mixed` parameter is opaque too) and the sister rector fixer, which
 * names bare flags without a type check.
 *
 * Type resolution uses whatever PHPStan extensions the consumer loads. In a
 * larastan-equipped app this resolves receivers (generic/inherited properties)
 * that bare PHPStan cannot — the gap the sister rector rule leaves behind.
 */
trait DetectsPositionalFlagArgument
{
    /**
     * @param  list<string>  $firstPartyNamespaces
     * @return array{method: string, argIndex: int, paramName: string, value: string}|null
     */
    private function flagSiteForMethodCall(MethodCall $node, Scope $scope, array $firstPartyNamespaces): ?array
    {
        if (! $node->name instanceof Identifier) {
            return null;
        }

        return $this->instanceCallFlagSite($node->var, $node->name->name, $node->getArgs(), $scope, $firstPartyNamespaces);
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     * @return array{method: string, argIndex: int, paramName: string, value: string}|null
     */
    private function flagSiteForNullsafeMethodCall(NullsafeMethodCall $node, Scope $scope, array $firstPartyNamespaces): ?array
    {
        if (! $node->name instanceof Identifier) {
            return null;
        }

        return $this->instanceCallFlagSite($node->var, $node->name->name, $node->getArgs(), $scope, $firstPartyNamespaces);
    }

    /**
     * Shared resolution for `$obj->m(...)` and `$obj?->m(...)` — both carry a
     * receiver expression, a method name, and args; the receiver type resolution
     * is identical (a nullable receiver collapses via removeNull).
     *
     * @param  array<Arg>  $args
     * @param  list<string>  $firstPartyNamespaces
     * @return array{method: string, argIndex: int, paramName: string, value: string}|null
     */
    private function instanceCallFlagSite(Expr $receiver, string $methodName, array $args, Scope $scope, array $firstPartyNamespaces): ?array
    {
        $flagIndex = $this->lastBareFlagIndex($args);

        if ($flagIndex === null) {
            return null;
        }

        $classReflections = TypeCombinator::removeNull($scope->getType($receiver))->getObjectClassReflections();

        // Single concrete receiver only (matches the sister rector rule). A union
        // receiver whose members name the flag parameter differently would make
        // the suggested name ambiguous; cross-member agreement is later scope.
        if (count($classReflections) !== 1 || ! $classReflections[0]->hasMethod($methodName)) {
            return null;
        }

        return $this->flagRecord($classReflections[0]->getMethod($methodName, $scope), $methodName, $args, $flagIndex, $scope, $firstPartyNamespaces);
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     * @return array{method: string, argIndex: int, paramName: string, value: string}|null
     */
    private function flagSiteForStaticCall(StaticCall $node, Scope $scope, ReflectionProvider $reflectionProvider, array $firstPartyNamespaces): ?array
    {
        if (! $node->class instanceof Name || ! $node->name instanceof Identifier) {
            return null;
        }

        $args = $node->getArgs();
        $flagIndex = $this->lastBareFlagIndex($args);

        if ($flagIndex === null) {
            return null;
        }

        $className = $scope->resolveName($node->class);

        if (! $reflectionProvider->hasClass($className)) {
            return null;
        }

        $classReflection = $reflectionProvider->getClass($className);

        if (! $classReflection->hasMethod($node->name->name)) {
            return null;
        }

        return $this->flagRecord($classReflection->getMethod($node->name->name, $scope), $node->name->name, $args, $flagIndex, $scope, $firstPartyNamespaces);
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     * @return array{method: string, argIndex: int, paramName: string, value: string}|null
     */
    private function flagSiteForNew(New_ $node, Scope $scope, ReflectionProvider $reflectionProvider, array $firstPartyNamespaces): ?array
    {
        if (! $node->class instanceof Name) {
            return null;
        }

        $args = $node->getArgs();
        $flagIndex = $this->lastBareFlagIndex($args);

        if ($flagIndex === null) {
            return null;
        }

        $className = $scope->resolveName($node->class);

        if (! $reflectionProvider->hasClass($className)) {
            return null;
        }

        $classReflection = $reflectionProvider->getClass($className);

        if (! $classReflection->hasConstructor()) {
            return null;
        }

        // The rector rule keys constructor records on the resolved FQCN.
        return $this->flagRecord($classReflection->getConstructor(), $className, $args, $flagIndex, $scope, $firstPartyNamespaces);
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    private function positionalFlagErrorForMethodCall(MethodCall $node, Scope $scope, array $firstPartyNamespaces): ?IdentifierRuleError
    {
        return $this->flagErrorFromSite($this->flagSiteForMethodCall($node, $scope, $firstPartyNamespaces));
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    private function positionalFlagErrorForNullsafeMethodCall(NullsafeMethodCall $node, Scope $scope, array $firstPartyNamespaces): ?IdentifierRuleError
    {
        return $this->flagErrorFromSite($this->flagSiteForNullsafeMethodCall($node, $scope, $firstPartyNamespaces));
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    private function positionalFlagErrorForStaticCall(StaticCall $node, Scope $scope, ReflectionProvider $reflectionProvider, array $firstPartyNamespaces): ?IdentifierRuleError
    {
        return $this->flagErrorFromSite($this->flagSiteForStaticCall($node, $scope, $reflectionProvider, $firstPartyNamespaces));
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    private function positionalFlagErrorForNew(New_ $node, Scope $scope, ReflectionProvider $reflectionProvider, array $firstPartyNamespaces): ?IdentifierRuleError
    {
        return $this->flagErrorFromSite($this->flagSiteForNew($node, $scope, $reflectionProvider, $firstPartyNamespaces));
    }

    /**
     * @param  array{method: string, argIndex: int, paramName: string, value: string}|null  $site
     */
    private function flagErrorFromSite(?array $site): ?IdentifierRuleError
    {
        return $site === null ? null : $this->flagError($site['paramName']);
    }

    /**
     * Builds the shared `{method, argIndex, paramName, value}` record, gating on
     * the member's declaring class (not the receiver) so an App\ class inheriting
     * a vendor method is not named against vendor-declared, non-semver-stable
     * parameter names.
     *
     * @param  array<Arg>  $args
     * @param  list<string>  $firstPartyNamespaces
     * @return array{method: string, argIndex: int, paramName: string, value: string}|null
     */
    private function flagRecord(ExtendedMethodReflection $method, string $methodLabel, array $args, int $flagIndex, Scope $scope, array $firstPartyNamespaces): ?array
    {
        if (! $this->isFirstPartyClass($method->getDeclaringClass()->getName(), $firstPartyNamespaces)) {
            return null;
        }

        $variants = $method->getVariants();

        // Parameter names and the variadic flag are static metadata, independent
        // of the argument types — so the expensive arg-based variant selection is
        // only needed to disambiguate overloads. The overwhelmingly common
        // single-variant case can read the parameters directly.
        $parameters = (count($variants) === 1
            ? $variants[0]
            : ParametersAcceptorSelector::selectFromArgs($scope, $args, $variants)
        )->getParameters();
        $parameter = $parameters[$flagIndex] ?? null;

        if ($parameter === null || $parameter->isVariadic()) {
            return null;
        }

        return [
            'method' => $methodLabel,
            'argIndex' => $flagIndex,
            'paramName' => $parameter->getName(),
            'value' => $this->flagLiteral($args[$flagIndex]),
        ];
    }

    private function flagError(string $paramName): IdentifierRuleError
    {
        return RuleErrorBuilder::message(sprintf(
            'Pass a named argument (%s: ...) for the bool/null flag — it is opaque positionally.',
            $paramName,
        ))
            ->identifier('hihaho.conventions.positionalFlagArgument')
            ->tip('Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).')
            ->build();
    }

    /**
     * Index of the final argument when it is a bare bool/null flag and every
     * argument is positional, else null. The last-arg check runs first as the
     * cheap reject on the hot path.
     *
     * @param  array<Arg>  $args
     */
    private function lastBareFlagIndex(array $args): ?int
    {
        if ($args === []) {
            return null;
        }

        $lastIndex = count($args) - 1;

        if (! $this->isBareBoolOrNullFlag($args[$lastIndex])) {
            return null;
        }

        foreach ($args as $arg) {
            if ($arg->name instanceof Identifier || $arg->unpack) {
                return null;
            }
        }

        return $lastIndex;
    }

    private function isBareBoolOrNullFlag(Arg $arg): bool
    {
        if ($arg->name instanceof Identifier || $arg->unpack) {
            return false;
        }

        if (! $arg->value instanceof ConstFetch) {
            return false;
        }

        return match ($arg->value->name->toLowerString()) {
            'true', 'false', 'null' => true,
            default => false,
        };
    }

    private function flagLiteral(Arg $arg): string
    {
        return $arg->value instanceof ConstFetch ? $arg->value->name->toLowerString() : '';
    }

    /**
     * @param  list<string>  $namespaces
     */
    private function isFirstPartyClass(string $className, array $namespaces): bool
    {
        foreach ($namespaces as $namespace) {
            if (str_starts_with($className, rtrim($namespace, '\\') . '\\')) {
                return true;
            }
        }

        return false;
    }
}
