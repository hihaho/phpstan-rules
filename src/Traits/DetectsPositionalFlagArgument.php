<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Traits;

use Hihaho\PhpstanRules\Support\TrailingFlagRun;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\TypeCombinator;

/**
 * Detects bare `true`/`false`/`null` literals passed positionally at the end of a
 * first-party method, static, or constructor call — where naming them
 * (`paramName: false`) makes the call self-documenting — and fixes them.
 *
 * Scope: the trailing run of {@see TrailingFlagRun}. Any bare flag on a named,
 * non-variadic parameter qualifies — the parameter is NOT required to be
 * bool-typed, matching the convention (a bare null on a `?Object`/`mixed`
 * parameter is opaque too).
 *
 * Type resolution uses whatever PHPStan extensions the consumer loads, so in a
 * larastan-equipped app this resolves receivers (generic/inherited properties)
 * that bare PHPStan cannot.
 *
 * One call yields one error with one fix per argument. PHPStan applies an error's
 * fixes together or not at all, so a skipped fix can never leave a positional
 * argument behind a named one.
 */
trait DetectsPositionalFlagArgument
{
    /**
     * @param  list<string>  $firstPartyNamespaces
     * @return list<array{arg: Arg, paramName: string}>
     */
    private function flagSiteForMethodCall(MethodCall $node, Scope $scope, array $firstPartyNamespaces): array
    {
        if (! $node->name instanceof Identifier || $this->isVirtualNullsafeCall($node)) {
            return [];
        }

        return $this->instanceCallFlagSite($node->var, $node->name->name, $node->getArgs(), $scope, $firstPartyNamespaces);
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     * @return list<array{arg: Arg, paramName: string}>
     */
    private function flagSiteForNullsafeMethodCall(NullsafeMethodCall $node, Scope $scope, array $firstPartyNamespaces): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        return $this->instanceCallFlagSite($node->var, $node->name->name, $node->getArgs(), $scope, $firstPartyNamespaces);
    }

    /**
     * PHPStan dispatches a synthetic `MethodCall` for the non-null branch of
     * `$obj?->m(...)`, so skipping it here is what keeps the flag error from
     * being reported twice alongside the NullsafeMethodCall rule.
     *
     * It belongs in this trait, not in the rules: the other `MethodCall` checks
     * (debug, unsafe request data, form-request fields) have no nullsafe twin
     * and get their `?->` coverage from exactly this synthetic node.
     */
    private function isVirtualNullsafeCall(MethodCall $node): bool
    {
        return $node->getAttribute('virtualNullsafeMethodCall') === true;
    }

    /**
     * Shared resolution for `$obj->m(...)` and `$obj?->m(...)` — both carry a
     * receiver expression, a method name, and args; the receiver type resolution
     * is identical (a nullable receiver collapses via removeNull).
     *
     * The receiver type must certainly have the method. That leaves intersections
     * alone but declines a union with a member that lacks it: the call may land on
     * that member, and if it forwards through `__call`, a named argument arrives
     * under a string key instead of its position.
     *
     * @param  array<Arg>  $args
     * @param  list<string>  $firstPartyNamespaces
     * @return list<array{arg: Arg, paramName: string}>
     */
    private function instanceCallFlagSite(Expr $receiver, string $methodName, array $args, Scope $scope, array $firstPartyNamespaces): array
    {
        if (! TrailingFlagRun::mayContainFlag($args)) {
            return [];
        }

        if ($scope->isInTrait() && $receiver instanceof Variable && $receiver->name === 'this') {
            return [];
        }

        $receiverType = TypeCombinator::removeNull($scope->getType($receiver));

        if (! $receiverType->hasMethod($methodName)->yes()) {
            return [];
        }

        return $this->agreedFlagSite($receiverType->getObjectClassReflections(), $methodName, $args, $scope, $firstPartyNamespaces);
    }

    /**
     * Resolves the arguments to name across every receiver member that declares
     * the method — an intersection or union receiver yields one reflection per
     * member. Declines when no member declares it, when any declarer would decline
     * on its own (vendor declaring class, variadic parameter), or when two
     * declarers disagree on the parameter names — only that disagreement makes the
     * fix ambiguous.
     *
     * Comparing the names is enough: every run ends at the same last argument, so
     * two runs of the same length cover the same arguments.
     *
     * @param  list<ClassReflection>  $classReflections
     * @param  array<Arg>  $args
     * @param  list<string>  $firstPartyNamespaces
     * @return list<array{arg: Arg, paramName: string}>
     */
    private function agreedFlagSite(array $classReflections, string $methodName, array $args, Scope $scope, array $firstPartyNamespaces): array
    {
        $site = null;

        foreach ($classReflections as $classReflection) {
            if (! $classReflection->hasMethod($methodName)) {
                continue;
            }

            $namings = $this->flagNamings($classReflection->getMethod($methodName, $scope), $args, $scope, $firstPartyNamespaces);

            if ($namings === [] || ($site !== null && array_column($namings, 'paramName') !== array_column($site, 'paramName'))) {
                return [];
            }

            $site = $namings;
        }

        return $site ?? [];
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     * @return list<array{arg: Arg, paramName: string}>
     */
    private function flagSiteForStaticCall(StaticCall $node, Scope $scope, ReflectionProvider $reflectionProvider, array $firstPartyNamespaces): array
    {
        if (! $node->class instanceof Name || ! $node->name instanceof Identifier) {
            return [];
        }

        $args = $node->getArgs();

        if (! TrailingFlagRun::mayContainFlag($args) || $this->isClassContextDependentInTrait($node->class, $scope)) {
            return [];
        }

        $className = $scope->resolveName($node->class);

        if (! $reflectionProvider->hasClass($className)) {
            return [];
        }

        $classReflection = $reflectionProvider->getClass($className);

        if (! $classReflection->hasMethod($node->name->name)) {
            return [];
        }

        return $this->flagNamings($classReflection->getMethod($node->name->name, $scope), $args, $scope, $firstPartyNamespaces);
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     * @return list<array{arg: Arg, paramName: string}>
     */
    private function flagSiteForNew(New_ $node, Scope $scope, ReflectionProvider $reflectionProvider, array $firstPartyNamespaces): array
    {
        $args = $node->getArgs();

        if (! TrailingFlagRun::mayContainFlag($args)) {
            return [];
        }

        $classReflection = $this->instantiatedClass($node->class, $scope, $reflectionProvider);

        if (! $classReflection instanceof ClassReflection || ! $classReflection->hasConstructor()) {
            return [];
        }

        return $this->flagNamings($classReflection->getConstructor(), $args, $scope, $firstPartyNamespaces);
    }

    /**
     * An anonymous class only gets past the first-party check on a constructor it
     * inherits: one it declares itself has no first-party declaring class.
     */
    private function instantiatedClass(Name|Expr|Class_ $class, Scope $scope, ReflectionProvider $reflectionProvider): ?ClassReflection
    {
        if ($class instanceof Class_) {
            return $reflectionProvider->getAnonymousClassReflection($class, $scope);
        }

        if (! $class instanceof Name || $this->isClassContextDependentInTrait($class, $scope)) {
            return null;
        }

        $className = $scope->resolveName($class);

        return $reflectionProvider->hasClass($className) ? $reflectionProvider->getClass($className) : null;
    }

    /**
     * A trait is analysed once per using class, and `self`/`static`/`parent` (like
     * `$this`) can resolve to a differently named parameter in each of them. A name
     * that is right for one using class breaks the other, so such calls are left alone.
     */
    private function isClassContextDependentInTrait(Name $class, Scope $scope): bool
    {
        return $scope->isInTrait() && $class->isSpecialClassName();
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    private function positionalFlagErrorForMethodCall(MethodCall $node, Scope $scope, array $firstPartyNamespaces): ?IdentifierRuleError
    {
        return $this->flagError($this->flagSiteForMethodCall($node, $scope, $firstPartyNamespaces));
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    private function positionalFlagErrorForNullsafeMethodCall(NullsafeMethodCall $node, Scope $scope, array $firstPartyNamespaces): ?IdentifierRuleError
    {
        return $this->flagError($this->flagSiteForNullsafeMethodCall($node, $scope, $firstPartyNamespaces));
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    private function positionalFlagErrorForStaticCall(StaticCall $node, Scope $scope, ReflectionProvider $reflectionProvider, array $firstPartyNamespaces): ?IdentifierRuleError
    {
        return $this->flagError($this->flagSiteForStaticCall($node, $scope, $reflectionProvider, $firstPartyNamespaces));
    }

    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    private function positionalFlagErrorForNew(New_ $node, Scope $scope, ReflectionProvider $reflectionProvider, array $firstPartyNamespaces): ?IdentifierRuleError
    {
        return $this->flagError($this->flagSiteForNew($node, $scope, $reflectionProvider, $firstPartyNamespaces));
    }

    /**
     * The arguments to name for one callee, gating on the member's declaring class
     * (not the receiver) so an App\ class inheriting a vendor method is not named
     * against vendor-declared, non-semver-stable parameter names.
     *
     * A `@no-named-arguments` callee declines: core reports named arguments on it
     * and fixes them back to positional, so naming them here would never settle.
     *
     * @param  array<Arg>  $args
     * @param  list<string>  $firstPartyNamespaces
     * @return list<array{arg: Arg, paramName: string}>
     */
    private function flagNamings(ExtendedMethodReflection $method, array $args, Scope $scope, array $firstPartyNamespaces): array
    {
        if (
            ! $this->isFirstPartyClass($method->getDeclaringClass()->getName(), $firstPartyNamespaces)
            || ! $method->acceptsNamedArguments()->yes()
        ) {
            return [];
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

        $args = array_values($args);
        $namings = [];

        foreach (TrailingFlagRun::indexes($args, $parameters) as $index) {
            $namings[] = ['arg' => $args[$index], 'paramName' => $parameters[$index]->getName()];
        }

        return $namings;
    }

    /**
     * @param  list<array{arg: Arg, paramName: string}>  $namings
     */
    private function flagError(array $namings): ?IdentifierRuleError
    {
        if ($namings === []) {
            return null;
        }

        $builder = RuleErrorBuilder::message($this->flagMessage(array_column($namings, 'paramName')))
            ->identifier('hihaho.conventions.positionalFlagArgument')
            ->tip('Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).');

        foreach ($namings as $naming) {
            $paramName = $naming['paramName'];

            $builder->fixNode($naming['arg'], static function (Arg $arg) use ($paramName): Arg {
                $arg->name = new Identifier($paramName);

                return $arg;
            });
        }

        return $builder->build();
    }

    /**
     * @param  list<string>  $paramNames
     */
    private function flagMessage(array $paramNames): string
    {
        if (count($paramNames) === 1) {
            return "Pass a named argument ({$paramNames[0]}: ...) for the bool/null flag — it is opaque positionally.";
        }

        $list = implode(', ', array_map(static fn (string $name): string => "{$name}: ...", $paramNames));

        return "Pass named arguments ({$list}) for the bool/null flags — they are opaque positionally.";
    }

    /**
     * @param  list<string>  $namespaces
     */
    private function isFirstPartyClass(string $className, array $namespaces): bool
    {
        return array_any($namespaces, fn (string $namespace) => str_starts_with($className, rtrim($namespace, '\\') . '\\'));
    }
}
