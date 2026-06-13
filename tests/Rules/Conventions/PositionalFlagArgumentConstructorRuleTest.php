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
}
