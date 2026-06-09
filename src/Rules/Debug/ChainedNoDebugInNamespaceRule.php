<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Debug;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @extends BaseNoDebugRule<MethodCall>
 */
final readonly class ChainedNoDebugInNamespaceRule extends BaseNoDebugRule
{
    private const string MESSAGE = 'No chained debug statements should be present in the %s namespace.';

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

        $methodName = $node->name->name;

        if (! $this->isDebugMethod($methodName)) {
            return [];
        }

        $namespace = $this->matchDebugNamespace($scope);

        if ($namespace === null) {
            return [];
        }

        if (! $this->isDebugHelperMethodCall($node, $scope, $methodName)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(self::MESSAGE, $namespace))
                ->identifier("hihaho.debug.noChainedDebugIn{$namespace}")
                ->build(),
        ];
    }
}
