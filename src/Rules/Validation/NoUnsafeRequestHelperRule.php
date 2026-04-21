<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Validation;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FuncCall>
 */
final readonly class NoUnsafeRequestHelperRule implements Rule
{
    use ChecksNamespace;

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

        if ($node->getArgs() === []) {
            return [];
        }

        if (! $this->reflectionProvider->hasFunction($node->name, $scope)) {
            return [];
        }

        if (strtolower($this->reflectionProvider->getFunction($node->name, $scope)->getName()) !== 'request') {
            return [];
        }

        if (! $this->isInConfiguredNamespace($scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Reading unvalidated request data via request(...) is not allowed. Use a FormRequest, $request->validated(), or $request->safe().'
            )
                ->identifier('hihaho.validation.noUnsafeRequestHelper')
                ->tip('Inject a FormRequest subclass, or call $request->validated() / $request->safe() before reading input.')
                ->build(),
        ];
    }

    private function isInConfiguredNamespace(Scope $scope): bool
    {
        return $this->namespaceStartsWithAny($scope, $this->namespaces)
            && ! $this->namespaceStartsWithAny($scope, $this->excludeNamespaces);
    }
}
