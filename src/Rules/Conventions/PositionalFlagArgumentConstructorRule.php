<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Rules\Conventions;

use Hihaho\PhpstanRules\Traits\DetectsPositionalFlagArgument;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * Flags a bare bool/null flag passed positionally as the last argument of a
 * first-party constructor call. Registered directly — `New_` has no combined
 * rule (constructor calls are far rarer than method calls, so the extra
 * dispatch is acceptable).
 *
 * @implements Rule<New_>
 */
final readonly class PositionalFlagArgumentConstructorRule implements Rule
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
        return New_::class;
    }

    /**
     * @param  New_  $node
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $error = $this->positionalFlagErrorForNew($node, $scope, $this->reflectionProvider, $this->firstPartyNamespaces);

        return $error instanceof IdentifierRuleError ? [$error] : [];
    }
}
