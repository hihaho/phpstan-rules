<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Html;

use Override;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InArrowFunctionNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * The arrow-function form of UnescapedHtmlFieldClosureRule: the body is the
 * returned value, judged with the arrow function's own scope.
 *
 * @implements Rule<InArrowFunctionNode>
 * @see UnescapedHtmlFieldRuleTest
 */
final readonly class UnescapedHtmlFieldArrowFunctionRule implements Rule
{
    public function __construct(
        private HtmlFieldReturnSink $sink,
    ) {}

    #[Override]
    public function getNodeType(): string
    {
        return InArrowFunctionNode::class;
    }

    /**
     * @param  InArrowFunctionNode  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $arrowFunction = $node->getOriginalNode();

        if (! $this->sink->isSink($arrowFunction, $scope)) {
            return [];
        }

        $error = $this->sink->returnError($arrowFunction->expr, $scope, $arrowFunction->expr->getStartLine());

        return $error instanceof IdentifierRuleError ? [$error] : [];
    }
}
