<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @extends BaseNoDebugRule<FuncCall>
 */
final readonly class NoDebugInNamespaceRule extends BaseNoDebugRule
{
    private const string MESSAGE = 'No debug statements should be present in the %s namespace.';

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

        if (! $this->isDebugFunction($node->name->toString())) {
            return [];
        }

        if ($this->namespaceStartsWith($scope, 'App')) {
            return [
                RuleErrorBuilder::message(sprintf(self::MESSAGE, 'App'))
                    ->identifier('hihaho.debug.noDebugInApp')
                    ->build(),
            ];
        }

        if ($this->namespaceStartsWith($scope, 'Tests')) {
            return [
                RuleErrorBuilder::message(sprintf(self::MESSAGE, 'Tests'))
                    ->identifier('hihaho.debug.noDebugInTests')
                    ->build(),
            ];
        }

        return [];
    }
}
