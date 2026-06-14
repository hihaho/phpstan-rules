<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Traits;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Shared detection for reading unvalidated request data through the global
 * `request()` helper — used by both the standalone NoUnsafeRequestHelperRule
 * and the registered CombinedFuncCallRule.
 *
 * The consuming class must provide `namespaceStartsWithAny()` (via the
 * ChecksNamespace trait, directly or through a parent).
 */
trait DetectsUnsafeRequestHelper
{
    /**
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    private function unsafeRequestHelperError(
        FuncCall $node,
        Name $name,
        Scope $scope,
        ReflectionProvider $reflectionProvider,
        array $namespaces,
        array $excludeNamespaces,
    ): ?IdentifierRuleError {
        if ($node->getArgs() === []) {
            return null;
        }

        // Cheap pre-filter: skip reflection unless the short name could possibly
        // resolve to the global `request()` helper.
        if (strtolower($name->getLast()) !== 'request') {
            return null;
        }

        if (! $this->namespaceStartsWithAny($scope, $namespaces) || $this->namespaceStartsWithAny($scope, $excludeNamespaces)) {
            return null;
        }

        if (! $reflectionProvider->hasFunction($name, $scope)) {
            return null;
        }

        if (strtolower($reflectionProvider->getFunction($name, $scope)->getName()) !== 'request') {
            return null;
        }

        return RuleErrorBuilder::message(sprintf(
            'Reading unvalidated request data via %s is not allowed. Use a FormRequest, $request->validated(), or $request->safe().',
            $this->requestHelperCallLabel($node),
        ))
            ->identifier('hihaho.validation.noUnsafeRequestHelper')
            ->tip('Inject a FormRequest (or Request typehint) and consume via $request->validated() / $request->safe() instead of the global helper.')
            ->build();
    }

    private function requestHelperCallLabel(FuncCall $node): string
    {
        $firstArg = $node->getArgs()[0]->value;

        if ($firstArg instanceof String_) {
            return "request('{$firstArg->value}')";
        }

        return 'request(...)';
    }
}
