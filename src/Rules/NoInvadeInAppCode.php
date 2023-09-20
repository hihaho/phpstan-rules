<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\FuncCall>
 */
class NoInvadeInAppCode implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Name) {
            return [];
        }

        if ($node->name->toString() === 'Livewire\invade') {
            return [
                RuleErrorBuilder::message(
                    'Usage of `\Livewire\invade` is disallowed, please use the global `invade` from spatie/invade'
                )->build(),
            ];
        }

        if ($node->name->toString() !== 'invade') {
            return [];
        }

        if (! str_starts_with($scope->getNamespace(), 'App')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Usage of method `invade` is not allowed in the App namespace.'
            )->build(),
        ];
    }
}
