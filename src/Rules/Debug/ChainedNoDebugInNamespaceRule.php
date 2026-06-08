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
