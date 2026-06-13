<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Conventions;

use Hihaho\PhpstanRules\Traits\DetectsPositionalFlagArgument;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * Flags a bare bool/null flag passed positionally as the last argument of a
 * first-party static call. The registered enforcement lives in
 * CombinedStaticCallRule; this standalone rule is the tested twin.
 *
 * @implements Rule<StaticCall>
 */
final readonly class PositionalFlagArgumentStaticCallRule implements Rule
{
    use DetectsPositionalFlagArgument;

    /**
     * @param  list<string>  $firstPartyNamespaces
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private array $firstPartyNamespaces,
    ) {}

    #[Override]
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param  StaticCall  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $error = $this->positionalFlagErrorForStaticCall($node, $scope, $this->reflectionProvider, $this->firstPartyNamespaces);

        return $error instanceof IdentifierRuleError ? [$error] : [];
    }
}
