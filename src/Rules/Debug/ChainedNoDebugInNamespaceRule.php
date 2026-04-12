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

        $methodName = $node->name->toString();

        if (! $this->isDebugMethod($methodName)) {
            return [];
        }

        if (! $this->isDebugHelperMethodCall($node, $scope, $methodName)) {
            return [];
        }

        if ($this->namespaceStartsWith($scope, 'App')) {
            return [
                RuleErrorBuilder::message(sprintf(self::MESSAGE, 'App'))
                    ->identifier('hihaho.debug.noChainedDebugInApp')
                    ->build(),
            ];
        }

        if ($this->namespaceStartsWith($scope, 'Tests')) {
            return [
                RuleErrorBuilder::message(sprintf(self::MESSAGE, 'Tests'))
                    ->identifier('hihaho.debug.noChainedDebugInTests')
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * A `->dump()` / `->dd()` chain is only a real debug call when the method
     * is declared by a Laravel-framework class or trait. Unrelated user methods
     * that happen to share the name (e.g. a custom `->dump()` on a value object)
     * are not flagged. Unknown receiver types are skipped.
     */
    private function isDebugHelperMethodCall(MethodCall $node, Scope $scope, string $methodName): bool
    {
        $classReflections = $scope->getType($node->var)->getObjectClassReflections();

        if ($classReflections === []) {
            return false;
        }

        foreach ($classReflections as $classReflection) {
            if (! $classReflection->hasMethod($methodName)) {
                continue;
            }

            $declaringClassName = $classReflection->getMethod($methodName, $scope)->getDeclaringClass()->getName();

            if (str_starts_with($declaringClassName, self::LARAVEL_NAMESPACE_PREFIX)) {
                return true;
            }
        }

        return false;
    }
}
