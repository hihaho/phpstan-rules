<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Validation;

use Hihaho\PhpstanRules\Rules\Validation\NoUnsafeRequestHelperRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<NoUnsafeRequestHelperRule>
 */
final class NoUnsafeRequestHelperRuleTest extends RuleTestCase
{
    private const string MESSAGE = 'Reading unvalidated request data via request(...) is not allowed. Use a FormRequest, $request->validated(), or $request->safe().';

    private const string TIP = 'Inject a FormRequest subclass, or call $request->validated() / $request->safe() before reading input.';

    #[Override]
    protected function getRule(): Rule
    {
        return new NoUnsafeRequestHelperRule(
            namespaces: ['App'],
            excludeNamespaces: ['App\\Providers'],
            reflectionProvider: self::createReflectionProvider(),
        );
    }

    #[Test]
    public function flags_request_helper_with_argument(): void
    {
        $this->analyse([__DIR__ . '/stubs/RequestHelperWithArgStub.php'], [
            [self::MESSAGE, 13, self::TIP],
            [self::MESSAGE, 14, self::TIP],
        ]);
    }

    #[Test]
    public function does_not_flag_zero_arg_request_helper(): void
    {
        $this->analyse([__DIR__ . '/stubs/RequestHelperNoArgStub.php'], []);
    }

    #[Test]
    public function does_not_flag_request_helper_outside_configured_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/RequestHelperOutsideNamespaceStub.php'], []);
    }

    #[Test]
    public function does_not_flag_request_helper_inside_excluded_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/ProvidersNamespaceStub.php'], []);
    }

    #[Test]
    public function flags_fully_qualified_and_mixed_case_request_helper(): void
    {
        $this->analyse([__DIR__ . '/stubs/FullyQualifiedRequestHelperStub.php'], [
            [self::MESSAGE, 13, self::TIP],
            [self::MESSAGE, 14, self::TIP],
        ]);
    }

    #[Test]
    public function error_uses_correct_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/RequestHelperWithArgStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.validation.noUnsafeRequestHelper', $error->getIdentifier());
        }
    }
}
