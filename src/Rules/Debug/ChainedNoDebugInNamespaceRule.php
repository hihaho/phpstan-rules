<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;

/**
 * @extends BaseNoDebugRule<MethodCall>
 */
final readonly class ChainedNoDebugInNamespaceRule extends BaseNoDebugRule
{
    #[Override]
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param  MethodCall  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $error = $this->chainedDebugError($node, $node->name->name, $scope);

        return $error instanceof IdentifierRuleError ? [$error] : [];
    }
}
