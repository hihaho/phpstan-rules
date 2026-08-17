<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Conventions;

use Hihaho\PhpstanRules\Rules\Conventions\PositionalFlagArgumentNullsafeMethodCallRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<PositionalFlagArgumentNullsafeMethodCallRule>
 */
final class PositionalFlagArgumentNullsafeMethodCallRuleTest extends RuleTestCase
{
    #[Override]
    protected function getRule(): Rule
    {
        return new PositionalFlagArgumentNullsafeMethodCallRule(
            firstPartyNamespaces: ['App', 'Database\\Factories', 'Tests'],
        );
    }

    private function message(string $param): string
    {
        return "Pass a named argument ({$param}: ...) for the bool/null flag — it is opaque positionally.";
    }

    private function tip(): string
    {
        return 'Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).';
    }

    #[Test]
    public function flags_a_trailing_flag_on_a_first_party_nullsafe_call(): void
    {
        $this->analyse([__DIR__ . '/stubs/NullsafeFlagCallStub.php'], [
            [$this->message('active'), 16, $this->tip()],
        ]);
    }

    /** The nullsafe hops are this rule's alone; the plain call in that chain belongs to the MethodCall rule. */
    #[Test]
    public function flags_only_the_nullsafe_hops_of_a_mixed_chain(): void
    {
        $this->analyse([__DIR__ . '/stubs/NullsafeChainFlagCallStub.php'], [
            [$this->message('active'), 22, $this->tip()],
            [$this->message('active'), 23, $this->tip()],
        ]);
    }
}
