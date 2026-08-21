<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Conventions;

use Hihaho\PhpstanRules\Rules\Conventions\PositionalFlagArgumentMethodCallRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<PositionalFlagArgumentMethodCallRule>
 */
final class PositionalFlagArgumentMethodCallRuleTest extends RuleTestCase
{
    #[Override]
    protected function getRule(): Rule
    {
        return new PositionalFlagArgumentMethodCallRule(
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
    public function flags_a_trailing_bool_or_null_flag_on_a_first_party_method(): void
    {
        $this->analyse([__DIR__ . '/stubs/FlagMethodCallStub.php'], [
            [$this->message('active'), 22, $this->tip()],
            [$this->message('option'), 23, $this->tip()],
        ]);
    }

    #[Test]
    public function does_not_flag_a_call_on_a_non_first_party_class(): void
    {
        $this->analyse([__DIR__ . '/stubs/NonFirstPartyMethodCallStub.php'], []);
    }

    #[Test]
    public function flags_a_bare_null_on_any_named_parameter_not_only_bool(): void
    {
        $this->analyse([__DIR__ . '/stubs/NonBoolNullArgStub.php'], [
            [$this->message('id'), 18, $this->tip()],
            [$this->message('name'), 19, $this->tip()],
        ]);
    }

    #[Test]
    public function does_not_flag_a_vendor_method_inherited_by_a_first_party_class(): void
    {
        $this->analyse([__DIR__ . '/stubs/InheritedVendorMethodStub.php'], []);
    }

    /**
     * PHPStan's synthetic node for the non-null branch would otherwise duplicate
     * what PositionalFlagArgumentNullsafeMethodCallRule reports for this site.
     */
    #[Test]
    public function does_not_flag_a_nullsafe_call_left_to_the_nullsafe_rule(): void
    {
        $this->analyse([__DIR__ . '/stubs/NullsafeFlagCallStub.php'], []);
    }

    /** A plain `->` call after a nullsafe hop is not synthetic, and no nullsafe rule covers it. */
    #[Test]
    public function still_flags_a_plain_call_after_a_nullsafe_hop(): void
    {
        $this->analyse([__DIR__ . '/stubs/NullsafeChainFlagCallStub.php'], [
            [$this->message('active'), 21, $this->tip()],
        ]);
    }

    /**
     * An intersection receiver flags when exactly one member declares the
     * method, and when multiple declarers agree on the flag parameter's name
     * (once, not per member). It stays silent when the declarers disagree on
     * the name, or when one of them is vendor-declared. A union receiver
     * resolves the same way; a member that lacks the method entirely is
     * PHPStan's own undefined-method error to report, not ambiguity.
     */
    #[Test]
    public function resolves_intersection_receivers_by_declarer_agreement(): void
    {
        $this->analyse([__DIR__ . '/stubs/IntersectionFlagCallStub.php'], [
            [$this->message('active'), 43, $this->tip()],
            [$this->message('short'), 48, $this->tip()],
            [$this->message('short'), 63, $this->tip()],
        ]);
    }

    #[Test]
    public function error_uses_correct_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/FlagMethodCallStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.conventions.positionalFlagArgument', $error->getIdentifier());
        }
    }
}
