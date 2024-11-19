<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @extends BaseNoDebugRule<\PhpParser\Node\Expr\MethodCall>
 */
class ChainedNoDebugInNamespaceRule extends BaseNoDebugRule
{
    protected string $message = 'No chained debug statements should be present in the %s namespace.';

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Identifier) {
            return [];
        }

        if ($this->isDisallowedStatement($node->name->toString())) {
            if ($this->namespaceStartsWith($scope, 'App')) {
                return [
                    RuleErrorBuilder::message(sprintf($this->message, 'App'))
                        ->identifier('hihaho.debug.noChainedDebugInApp')
                        ->build(),
                ];
            }

            if ($this->namespaceStartsWith($scope, 'Tests')) {
                return [
                    RuleErrorBuilder::message(sprintf($this->message, 'Tests'))
                        ->identifier('hihaho.debug.noChainedDebugInTests')
                        ->build(),
                ];
            }
        }

        return [];
    }
}
