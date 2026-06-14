<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Conventions;

use Hihaho\PhpstanRules\Traits\DetectsPositionalFlagArgument;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * Flags a bare bool/null flag passed positionally as the last argument of a
 * first-party nullsafe method call (`$obj?->method(..., true)`). Registered
 * directly — `NullsafeMethodCall` has no combined rule, and the receiver
 * resolves via the scope type just like a plain method call.
 *
 * @implements Rule<NullsafeMethodCall>
 */
final readonly class PositionalFlagArgumentNullsafeMethodCallRule implements Rule
{
    use DetectsPositionalFlagArgument;

    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    public function __construct(private array $firstPartyNamespaces) {}

    #[Override]
    public function getNodeType(): string
    {
        return NullsafeMethodCall::class;
    }

    /**
     * @param  NullsafeMethodCall  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $error = $this->positionalFlagErrorForNullsafeMethodCall($node, $scope, $this->firstPartyNamespaces);

        return $error instanceof IdentifierRuleError ? [$error] : [];
    }
}
