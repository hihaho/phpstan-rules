<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Conventions;

use Hihaho\PhpstanRules\Rules\Conventions\PositionalFlagArgumentConstructorRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<PositionalFlagArgumentConstructorRule>
 */
final class PositionalFlagArgumentConstructorRuleTest extends RuleTestCase
{
    #[Override]
    protected function getRule(): Rule
    {
        return new PositionalFlagArgumentConstructorRule(
            $this->createReflectionProvider(),
            firstPartyNamespaces: ['App', 'Database\\Factories', 'Tests'],
        );
    }

    #[Test]
    public function flags_a_trailing_flag_on_a_first_party_constructor_only(): void
    {
        $this->analyse([__DIR__ . '/stubs/ConstructorFlagCallStub.php'], [
            [
                'Pass a named argument (visible: ...) for the bool/null flag — it is opaque positionally.',
                23,
                'Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).',
            ],
        ]);
    }

    #[Test]
    public function fixes_constructor_calls_including_consecutive_flags_and_a_named_tail(): void
    {
        $this->fix(__DIR__ . '/stubs/FlagFixConstructorCallStub.php', __DIR__ . '/stubs/FlagFixConstructorCallStub.php.fixed');
    }

    /**
     * An inherited first-party constructor is reported. One the anonymous class
     * declares itself is not first-party, and a vendor parent's constructor stays excluded.
     */
    #[Test]
    public function flags_an_anonymous_class_only_when_it_inherits_a_first_party_constructor(): void
    {
        $this->analyse([__DIR__ . '/stubs/FlagAnonymousClassStub.php'], [
            [
                'Pass a named argument (readOnly: ...) for the bool/null flag — it is opaque positionally.',
                23,
                'Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).',
            ],
            [
                'Pass named arguments (readOnly: ..., lazy: ...) for the bool/null flags — they are opaque positionally.',
                24,
                'Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).',
            ],
            [
                'Pass a named argument (readOnly: ...) for the bool/null flag — it is opaque positionally.',
                25,
                'Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).',
            ],
        ]);
    }

    #[Test]
    public function fixes_anonymous_class_constructor_calls_and_keeps_the_class_body(): void
    {
        $this->fix(__DIR__ . '/stubs/FlagAnonymousClassStub.php', __DIR__ . '/stubs/FlagAnonymousClassStub.php.fixed');
    }

    /** `new self` resolves per using class, and the two using classes name the parameter differently. */
    #[Test]
    public function does_not_report_new_self_in_a_trait(): void
    {
        $this->analyse([__DIR__ . '/stubs/FlagTraitContextStub.php'], []);
    }
}
