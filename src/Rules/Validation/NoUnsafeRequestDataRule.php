<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Validation;

use Hihaho\PhpstanRules\Traits\ChecksNamespace;
use Hihaho\PhpstanRules\Traits\DetectsUnsafeRequestData;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<MethodCall>
 */
final readonly class NoUnsafeRequestDataRule implements Rule
{
    use ChecksNamespace;
    use DetectsUnsafeRequestData;

    /** @var array<string, true> */
    private array $unsafeMethodsLookup;

    /**
     * @param  list<string>  $unsafeMethods
     * @param  list<string>  $namespaces
     * @param  list<string>  $excludeNamespaces
     */
    public function __construct(
        array $unsafeMethods,
        private array $namespaces,
        private array $excludeNamespaces,
    ) {
        $this->unsafeMethodsLookup = array_fill_keys(array_map(strtolower(...), $unsafeMethods), true);
    }

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

        $error = $this->unsafeRequestDataError(
            $node,
            $node->name->name,
            $scope,
            $this->unsafeMethodsLookup,
            $this->namespaces,
            $this->excludeNamespaces,
        );

        return $error instanceof IdentifierRuleError ? [$error] : [];
    }
}
