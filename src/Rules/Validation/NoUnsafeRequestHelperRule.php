<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Validation;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use Hihaho\PhpstanRules\Traits\DetectsUnsafeRequestHelper;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<FuncCall>
 */
final readonly class NoUnsafeRequestHelperRule implements Rule
{
    use ChecksNamespace;
    use DetectsUnsafeRequestHelper;

    /**
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    public function __construct(
        private array $namespaces,
        private array $excludeNamespaces,
        private ReflectionProvider $reflectionProvider,
    ) {}

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

        $error = $this->unsafeRequestHelperError(
            $node,
            $node->name,
            $scope,
            $this->reflectionProvider,
            $this->namespaces,
            $this->excludeNamespaces,
        );

        return $error instanceof IdentifierRuleError ? [$error] : [];
    }
}
