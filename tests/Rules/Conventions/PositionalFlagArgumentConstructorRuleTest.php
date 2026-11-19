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

    /**
     * `new static` is late bound: a subclass constructor may rename the
     * parameter, and constructors are exempt from signature checks. `new self`
     * is reported.
     */
    #[Test]
    public function does_not_flag_a_late_bound_new_static(): void
    {
        $this->analyse([__DIR__ . '/stubs/LateBoundConstructorFlagStub.php'], [
            [
                'Pass a named argument (shown: ...) for the bool/null flag — it is opaque positionally.',
                16,
                'Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).',
            ],
        ]);
    }
}
