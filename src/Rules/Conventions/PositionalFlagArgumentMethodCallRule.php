<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Conventions;

use Hihaho\PhpstanRules\Traits\DetectsPositionalFlagArgument;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * Flags a bare bool/null flag passed positionally as the last argument of a
 * first-party method call. The registered enforcement lives in
 * CombinedMethodCallRule; this standalone rule is the tested twin.
 *
 * @implements Rule<MethodCall>
 */
final readonly class PositionalFlagArgumentMethodCallRule implements Rule
{
    use DetectsPositionalFlagArgument;

    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    public function __construct(private array $firstPartyNamespaces) {}

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
        $error = $this->positionalFlagErrorForMethodCall($node, $scope, $this->firstPartyNamespaces);

        return $error instanceof IdentifierRuleError ? [$error] : [];
    }
}
