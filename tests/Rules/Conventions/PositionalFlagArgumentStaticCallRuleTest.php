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

    private function message(string $param): string
    {
        return "Pass a named argument ({$param}: ...) for the bool/null flag — it is opaque positionally.";
    }

    private function tip(): string
    {
        return 'Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).';
    }

    /**
     * A `@method static` annotation, on a facade or elsewhere, goes through
     * `__callStatic`; a native static method, also on a facade, does not.
     * `static::` is late bound, so a subclass may rename the parameter. A
     * non-static method by class name is only a plain call from a subclass.
     */
    #[Test]
    public function does_not_flag_magic_or_late_bound_static_calls(): void
    {
        $this->analyse([__DIR__ . '/stubs/MagicStaticFlagCallStub.php'], [
            [$this->message('on'), 39, $this->tip()],
            [$this->message('on'), 47, $this->tip()],
            [$this->message('on'), 56, $this->tip()],
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
