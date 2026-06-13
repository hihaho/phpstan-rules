<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Traits;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
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
 * v1 scope: the last argument only, and only when every argument is positional
 * (no named or spread args), so the arg index maps directly to the parameter
 * index. This is the dominant case (`->getToken(..., false)`) and trailing-safe
 * by construction; the full trailing-run widening can come later.
 *
 * Type resolution uses whatever PHPStan extensions the consumer loads. In a
 * larastan-equipped app this resolves receivers (generic/inherited properties)
 * that bare PHPStan cannot — the gap the sister rector rule leaves behind.
 */
trait DetectsPositionalFlagArgument
{
    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    private function positionalFlagErrorForMethodCall(MethodCall $node, Scope $scope, array $firstPartyNamespaces): ?IdentifierRuleError
    {
        if (! $node->name instanceof Identifier) {
            return null;
        }

        $args = $node->getArgs();
        $flagIndex = $this->lastBareFlagIndex($args);

        if ($flagIndex === null) {
            return null;
        }

        $classReflections = TypeCombinator::removeNull($scope->getType($node->var))->getObjectClassReflections();

        // Single concrete receiver only (matches the sister rector rule). A union
        // receiver whose members name the flag parameter differently would make
        // the suggested name ambiguous; cross-member agreement is v2 scope.
        if (count($classReflections) !== 1 || ! $classReflections[0]->hasMethod($node->name->name)) {
            return null;
        }

        return $this->flagError($classReflections[0]->getMethod($node->name->name, $scope), $args, $flagIndex, $scope, $firstPartyNamespaces);
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    private function positionalFlagErrorForStaticCall(StaticCall $node, Scope $scope, ReflectionProvider $reflectionProvider, array $firstPartyNamespaces): ?IdentifierRuleError
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

        return $this->flagError($classReflection->getMethod($node->name->name, $scope), $args, $flagIndex, $scope, $firstPartyNamespaces);
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    private function positionalFlagErrorForNew(New_ $node, Scope $scope, ReflectionProvider $reflectionProvider, array $firstPartyNamespaces): ?IdentifierRuleError
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

        return $this->flagError($classReflection->getConstructor(), $args, $flagIndex, $scope, $firstPartyNamespaces);
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

        return in_array($arg->value->name->toLowerString(), ['true', 'false', 'null'], true);
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

    /**
     * @param  array<Arg>  $args
     * @param  list<string>  $firstPartyNamespaces
     */
    private function flagError(ExtendedMethodReflection $method, array $args, int $flagIndex, Scope $scope, array $firstPartyNamespaces): ?IdentifierRuleError
    {
        // Gate on where the member is DECLARED, not the receiver: an App\ class
        // inheriting a vendor method would otherwise be flagged against
        // vendor-declared parameter names, which are not semver-stable.
        if (! $this->isFirstPartyClass($method->getDeclaringClass()->getName(), $firstPartyNamespaces)) {
            return null;
        }

        $parameters = ParametersAcceptorSelector::selectFromArgs($scope, $args, $method->getVariants())->getParameters();
        $parameter = $parameters[$flagIndex] ?? null;

        if ($parameter === null || $parameter->isVariadic()) {
            return null;
        }

        // Only a bool / ?bool parameter is a flag. A bare null passed to a
        // nullable value type (?int, ?User) is not a flag, so skip it.
        if (! TypeCombinator::removeNull($parameter->getType())->isBoolean()->yes()) {
            return null;
        }

        return RuleErrorBuilder::message(sprintf(
            'Pass a named argument (%s: ...) for the bool/null flag — it is opaque positionally.',
            $parameter->getName(),
        ))
            ->identifier('hihaho.conventions.positionalFlagArgument')
            ->tip('Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).')
            ->build();
    }
}
