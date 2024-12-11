<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @extends BaseNoDebugRule<\PhpParser\Node\Expr\FuncCall>
 */
class NoDebugInNamespaceRule extends BaseNoDebugRule
{
    protected string $message = 'No debug statements should be present in the %s namespace.';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @param FuncCall $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Name) {
            return [];
        }

        if ($this->isDisallowedStatement($node->name->toString())) {
            if ($this->namespaceStartsWith($scope, 'App')) {
                return [
                    RuleErrorBuilder::message(sprintf($this->message, 'App'))
                        ->identifier('hihaho.debug.noDebugInApp')
                        ->build(),
                ];
            }

            if ($this->namespaceStartsWith($scope, 'Tests')) {
                return [
                    RuleErrorBuilder::message(sprintf($this->message, 'Tests'))
                        ->identifier('hihaho.debug.noDebugInTests')
                        ->build(),
                ];
            }
        }

        return [];
    }
}
