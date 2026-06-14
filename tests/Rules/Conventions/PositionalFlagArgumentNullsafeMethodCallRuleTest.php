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

    #[Test]
    public function flags_a_trailing_flag_on_a_first_party_nullsafe_call(): void
    {
        $this->analyse([__DIR__ . '/stubs/NullsafeFlagCallStub.php'], [
            [
                'Pass a named argument (active: ...) for the bool/null flag — it is opaque positionally.',
                16,
                'Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).',
            ],
        ]);
    }
}
