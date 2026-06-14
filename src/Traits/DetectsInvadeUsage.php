<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Traits;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Shared detection for disallowed `invade()` usage — used by both the standalone
 * NoInvadeInAppCode and the registered CombinedFuncCallRule.
 *
 * The consuming class must provide `namespaceStartsWith()` (via the
 * ChecksNamespace trait, directly or through a parent).
 */
trait DetectsInvadeUsage
{
    private function invadeUsageError(string $funcName, Scope $scope): ?IdentifierRuleError
    {
        if ($funcName === 'Livewire\invade') {
            return RuleErrorBuilder::message(
                'Usage of `\Livewire\invade` is disallowed, please use the global `invade` from spatie/invade.'
            )
                ->identifier('hihaho.generic.disallowedUsageOfLivewireInvade')
                ->build();
        }

        if ($funcName === 'invade' && $this->namespaceStartsWith($scope, 'App')) {
            return RuleErrorBuilder::message(
                'Usage of method `invade` is not allowed in the App namespace.'
            )
                ->identifier('hihaho.generic.noInvadeInAppCode')
                ->build();
        }

        return null;
    }
}
