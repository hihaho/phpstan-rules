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
     * the name, or when one of them is vendor-declared. A union receiver needs
     * every member to declare the method: at runtime the call may land on the
     * member that lacks it, where a `__call` would see a string key instead of a
     * position. A nullable receiver is not such a union.
     */
    #[Test]
    public function resolves_intersection_receivers_by_declarer_agreement(): void
    {
        $this->analyse([__DIR__ . '/stubs/IntersectionFlagCallStub.php'], [
            [$this->message('active'), 43, $this->tip()],
            [$this->message('short'), 48, $this->tip()],
            [$this->message('short'), 68, $this->tip()],
            [$this->message('short'), 88, $this->tip()],
        ]);
    }

    #[Test]
    public function reports_one_error_per_call_naming_the_whole_trailing_run(): void
    {
        $this->analyse([__DIR__ . '/stubs/FlagFixMethodCallStub.php'], [
            ['Pass named arguments (nullable: ..., strict: ...) for the bool/null flags — they are opaque positionally.', 27, $this->tip()],
            [$this->message('nullable'), 28, $this->tip()],
            [$this->message('on'), 29, $this->tip()],
            ['Pass named arguments (left: ..., right: ...) for the bool/null flags — they are opaque positionally.', 30, $this->tip()],
            ['Pass named arguments (left: ..., right: ...) for the bool/null flags — they are opaque positionally.', 31, $this->tip()],
            [$this->message('right'), 31, $this->tip()],
            [$this->message('on'), 32, $this->tip()],
            [$this->message('on'), 32, $this->tip()],
            [$this->message('nullable'), 34, $this->tip()],
            [$this->message('on'), 34, $this->tip()],
        ]);
    }

    /**
     * Covers consecutive flags, a flag before a named argument, a null literal, nested
     * calls and two calls on one line (both need a second round), and a multi-line
     * fluent chain, whose layout and comment must survive.
     */
    #[Test]
    public function fixes_every_reported_call_and_keeps_the_formatting(): void
    {
        $this->fix(__DIR__ . '/stubs/FlagFixMethodCallStub.php', __DIR__ . '/stubs/FlagFixMethodCallStub.php.fixed');
    }

    #[Test]
    public function fixes_receivers_whose_declarers_agree_and_leaves_the_others(): void
    {
        $this->fix(__DIR__ . '/stubs/IntersectionFlagCallStub.php', __DIR__ . '/stubs/IntersectionFlagCallStub.php.fixed');
    }

    /**
     * A flag before a positional, unpacked arguments, variadic and missing parameters,
     * already named flags, a first-class callable, a dynamic method name, and a
     * `@no-named-arguments` callee, whose named arguments core would fix straight back.
     */
    #[Test]
    public function does_not_report_calls_it_cannot_safely_name(): void
    {
        $this->analyse([__DIR__ . '/stubs/FlagSkippedCallsStub.php'], []);
    }

    /**
     * `$this` resolves per using class, and those two classes name the parameter
     * differently. The call on a typed object has one callee in every context.
     */
    #[Test]
    public function in_a_trait_only_fixes_calls_that_do_not_depend_on_the_using_class(): void
    {
        $this->analyse([__DIR__ . '/stubs/FlagTraitContextStub.php'], [
            [$this->message('enabled'), 19, $this->tip()],
            [$this->message('enabled'), 19, $this->tip()],
        ]);
        $this->fix(__DIR__ . '/stubs/FlagTraitContextStub.php', __DIR__ . '/stubs/FlagTraitContextStub.php.fixed');
    }

    #[Test]
    public function error_uses_correct_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/FlagMethodCallStub.php']);

        $this->assertNotSame([], $errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.conventions.positionalFlagArgument', $error->getIdentifier());
        }
    }
}
