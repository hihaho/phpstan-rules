<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FuncCall>
 */
final readonly class NoInvadeInAppCode implements Rule
{
    use ChecksNamespace;

    #[Override]
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @param  FuncCall  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Name) {
            return [];
        }

        $functionName = $node->name->toString();

        if ($functionName === 'Livewire\invade') {
            return [
                RuleErrorBuilder::message(
                    'Usage of `\Livewire\invade` is disallowed, please use the global `invade` from spatie/invade.'
                )
                    ->identifier('hihaho.generic.disallowedUsageOfLivewireInvade')
                    ->build(),
            ];
        }

        if ($functionName !== 'invade') {
            return [];
        }

        if (! $this->namespaceStartsWith($scope, 'App')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Usage of method `invade` is not allowed in the App namespace.'
            )
                ->identifier('hihaho.generic.noInvadeInAppCode')
                ->build(),
        ];
    }
}
