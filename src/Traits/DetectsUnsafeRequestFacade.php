<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Traits;

use Illuminate\Support\Facades\Request as RequestFacade;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Shared detection for reading unvalidated request data through the
 * `Illuminate\Support\Facades\Request` facade — used by both the standalone
 * NoUnsafeRequestFacadeRule and the registered CombinedStaticCallRule.
 *
 * The consuming class must provide `namespaceStartsWithAny()` (via the
 * ChecksNamespace trait, directly or through a parent).
 */
trait DetectsUnsafeRequestFacade
{
    /**
     * @param  array<string, true>  $unsafeMethodsLookup
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    private function unsafeRequestFacadeError(
        Name $class,
        string $methodName,
        Scope $scope,
        array $unsafeMethodsLookup,
        array $namespaces,
        array $excludeNamespaces,
    ): ?IdentifierRuleError {
        // Fast pre-filter: only 'Request' (exact case) and fully-qualified
        // variants can match the facade. getLast() avoids strtolower on misses.
        if ($class->getLast() !== 'Request') {
            return null;
        }

        if (strtolower($class->name) !== strtolower(RequestFacade::class)) {
            return null;
        }

        if (! isset($unsafeMethodsLookup[strtolower($methodName)])) {
            return null;
        }

        if (! $this->namespaceStartsWithAny($scope, $namespaces) || $this->namespaceStartsWithAny($scope, $excludeNamespaces)) {
            return null;
        }

        return RuleErrorBuilder::message(sprintf(
            'Reading unvalidated request data via %s::%s() is not allowed. Use a FormRequest, $request->validated(), or $request->safe().',
            RequestFacade::class,
            $methodName,
        ))
            ->identifier('hihaho.validation.noUnsafeRequestFacade')
            ->tip('Inject a FormRequest (or Request typehint) and consume via $request->validated() / $request->safe() instead of the Request facade.')
            ->build();
    }
}
