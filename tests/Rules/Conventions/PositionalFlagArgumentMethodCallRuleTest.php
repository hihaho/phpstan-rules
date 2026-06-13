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
    public function does_not_flag_a_null_passed_to_a_non_bool_nullable_parameter(): void
    {
        $this->analyse([__DIR__ . '/stubs/NonBoolNullArgStub.php'], []);
    }

    #[Test]
    public function does_not_flag_a_vendor_method_inherited_by_a_first_party_class(): void
    {
        $this->analyse([__DIR__ . '/stubs/InheritedVendorMethodStub.php'], []);
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
