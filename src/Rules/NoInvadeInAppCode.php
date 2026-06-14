<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use Hihaho\PhpstanRules\Traits\DetectsInvadeUsage;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<FuncCall>
 */
final readonly class NoInvadeInAppCode implements Rule
{
    use ChecksNamespace;
    use DetectsInvadeUsage;

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

        $error = $this->invadeUsageError($node->name->name, $scope);

        return $error instanceof IdentifierRuleError ? [$error] : [];
    }
}
