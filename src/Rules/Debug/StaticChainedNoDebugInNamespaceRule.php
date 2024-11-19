<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @extends BaseNoDebugRule<\PhpParser\Node\Expr\StaticCall>
 */
class StaticChainedNoDebugInNamespaceRule extends BaseNoDebugRule
{
    protected string $message = 'No statically called debug statements should be present in the %s namespace.';

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param StaticCall $node
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
                        ->identifier('hihaho.debug.noStaticChainedDebugInApp')
                        ->build(),
                ];
            }

            if ($this->namespaceStartsWith($scope, 'Tests')) {
                return [
                    RuleErrorBuilder::message(sprintf($this->message, 'Tests'))
                        ->identifier('hihaho.debug.noStaticChainedDebugInApp')
                        ->build(),
                ];
            }
        }

        return [];
    }
}
