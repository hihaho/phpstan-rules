<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements \PHPStan\Rules\Rule<FuncCall>
 */
class NoDebugInNamespaceRule extends BaseNoDebugRule implements Rule
{
    protected string $message = 'No debug statements should be present in the %s namespace.';

    public function __construct()
    {
        $this->haystack = [
            ...$this->haystack,
            'ddd',
            'ray',
            'print_r',
            'var_dump',
        ];
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Name) {
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
