<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Html;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClosureReturnStatementsNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * Each `return` of a Nova `->asHtml()` field closure is judged with the scope
 * at that return, so a `$value` parameter or a narrowed local is typed as the
 * closure sees it.
 *
 * @implements Rule<ClosureReturnStatementsNode>
 * @see UnescapedHtmlFieldRuleTest
 */
final readonly class UnescapedHtmlFieldClosureRule implements Rule
{
    public function __construct(
        private HtmlFieldReturnSink $sink,
    ) {}

    #[Override]
    public function getNodeType(): string
    {
        return ClosureReturnStatementsNode::class;
    }

    /**
     * @param  ClosureReturnStatementsNode  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->sink->isSink($node->getClosureExpr(), $scope)) {
            return [];
        }

        $errors = [];

        foreach ($node->getReturnStatements() as $returnStatement) {
            $returned = $returnStatement->getReturnNode()->expr;

            if (! $returned instanceof Expr) {
                continue;
            }

            $error = $this->sink->returnError($returned, $returnStatement->getScope(), $returnStatement->getReturnNode()->getStartLine());

            if ($error instanceof IdentifierRuleError) {
                $errors[] = $error;
            }
        }

        return $errors;
    }
}
