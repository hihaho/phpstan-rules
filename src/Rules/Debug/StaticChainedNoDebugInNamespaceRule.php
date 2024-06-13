<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements \PHPStan\Rules\Rule<StaticCall>
 */
class StaticChainedNoDebugInNamespaceRule extends BaseNoDebugRule implements Rule
{
    protected string $message = 'No statically called debug statements should be present in the %s namespace.';

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Identifier) {
            return [];
        }

        if (! $this->hasDisallowedStatements($node->name->toString())) {
            return [];
        }

        if ($message = $this->message($scope, 'App')) {
            return [
                RuleErrorBuilder::message($message)->build(),
            ];
        }

        if ($message = $this->message($scope, 'Test')) {
            return [
                RuleErrorBuilder::message($message)->build(),
            ];
        }

        return [];
    }
}
