<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\FuncCall>
 */
class NoDebugInNamespaceRule implements Rule
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

        if ($this->hasDisallowedStatements($node) && str_starts_with($scope->getNamespace(), 'App')) {
            return [
                RuleErrorBuilder::message(
                    'No debug statements should be present in the app namespace.'
                )->build(),
            ];
        }

        if ($this->hasDisallowedStatements($node) && str_starts_with($scope->getNamespace(), 'Test')) {
            return [
                RuleErrorBuilder::message(
                    'No debug statements should be present in the test namespace.'
                )->build(),
            ];
        }

        return [];
    }

    /** @param FuncCall $node */
    private function hasDisallowedStatements(Node $node): bool
    {
        return match ($node->name->toString()) {
            'dump',
            'dd',
            'ddd',
            'ray',
            'print_r',
            'var_dump' => true,
            default => false,
        };
    }
}
