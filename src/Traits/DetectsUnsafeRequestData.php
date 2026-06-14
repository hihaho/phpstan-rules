<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Traits;

use Illuminate\Http\Request;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Shared detection for "reading unvalidated request data via an unsafe method
 * on an Illuminate\Http\Request receiver" — used by both the standalone
 * NoUnsafeRequestDataRule and the registered CombinedMethodCallRule so the two
 * cannot drift.
 *
 * The consuming class must provide `namespaceStartsWithAny()` (via the
 * ChecksNamespace trait, directly or through a parent).
 */
trait DetectsUnsafeRequestData
{
    /**
     * @param  array<string, true>  $unsafeMethodsLookup
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    private function unsafeRequestDataError(
        MethodCall $node,
        string $methodName,
        Scope $scope,
        array $unsafeMethodsLookup,
        array $namespaces,
        array $excludeNamespaces,
    ): ?IdentifierRuleError {
        if (! isset($unsafeMethodsLookup[strtolower($methodName)])) {
            return null;
        }

        if (! $this->namespaceStartsWithAny($scope, $namespaces) || $this->namespaceStartsWithAny($scope, $excludeNamespaces)) {
            return null;
        }

        if ($this->unsafeRequestScopeIsRequest($scope)) {
            return null;
        }

        if (! $this->unsafeRequestTypeIsRequest($scope->getType($node->var))) {
            return null;
        }

        return RuleErrorBuilder::message(sprintf(
            'Reading unvalidated request data via %s() is not allowed. Use a FormRequest, $request->validated(), or $request->safe().',
            $methodName,
        ))
            ->identifier('hihaho.validation.noUnsafeRequestData')
            ->tip('Use $request->validated() or $request->safe() to consume validated data. For Stringable/int/bool accessors, $request->safe()->string(\'key\') mirrors $request->string(\'key\') against validated input.')
            ->build();
    }

    private function unsafeRequestScopeIsRequest(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        return $classReflection instanceof ClassReflection && $this->unsafeRequestClassIsRequest($classReflection->getName());
    }

    private function unsafeRequestTypeIsRequest(Type $type): bool
    {
        foreach ($type->getObjectClassNames() as $className) {
            if ($this->unsafeRequestClassIsRequest($className)) {
                return true;
            }
        }

        return false;
    }

    private function unsafeRequestClassIsRequest(string $className): bool
    {
        /** @var array<string, bool> $cache */
        static $cache = [];

        if (! array_key_exists($className, $cache)) {
            $cache[$className] = (new ObjectType(Request::class))->isSuperTypeOf(new ObjectType($className))->yes();
        }

        return $cache[$className];
    }
}
