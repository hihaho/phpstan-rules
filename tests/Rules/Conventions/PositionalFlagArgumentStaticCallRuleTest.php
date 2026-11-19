<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Conventions;

use Hihaho\PhpstanRules\Rules\Conventions\PositionalFlagArgumentStaticCallRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<PositionalFlagArgumentStaticCallRule>
 */
final class PositionalFlagArgumentStaticCallRuleTest extends RuleTestCase
{
    #[Override]
    protected function getRule(): Rule
    {
        return new PositionalFlagArgumentStaticCallRule(
            $this->createReflectionProvider(),
            firstPartyNamespaces: ['App', 'Database\\Factories', 'Tests'],
        );
    }

    /**
     * Facade static calls go through `__callStatic`, as does a `@method static`
     * annotation. `static::` is late bound, so a subclass may rename the
     * parameter. Only the `self::` call is reported.
     */
    #[Test]
    public function does_not_flag_magic_facade_or_late_bound_static_calls(): void
    {
        $this->analyse([__DIR__ . '/stubs/MagicStaticFlagCallStub.php'], [
            [
                'Pass a named argument (on: ...) for the bool/null flag — it is opaque positionally.',
                37,
                'Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).',
            ],
        ]);
    }

    #[Test]
    public function flags_a_trailing_flag_on_a_first_party_static_call_only(): void
    {
        $this->analyse([__DIR__ . '/stubs/StaticFlagCallStub.php'], [
            [
                'Pass a named argument (on: ...) for the bool/null flag — it is opaque positionally.',
                23,
                'Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).',
            ],
        ]);
    }
}
