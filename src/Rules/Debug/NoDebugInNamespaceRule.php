<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;

/**
 * @extends BaseNoDebugRule<FuncCall>
 */
final readonly class NoDebugInNamespaceRule extends BaseNoDebugRule
{
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

        $error = $this->funcDebugError($node->name->name, $scope);

        return $error instanceof IdentifierRuleError ? [$error] : [];
    }
}
