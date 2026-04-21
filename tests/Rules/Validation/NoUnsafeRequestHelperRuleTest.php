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
    private const string MESSAGE_PATTERN = 'Reading unvalidated request data via %s is not allowed. Use a FormRequest, $request->validated(), or $request->safe().';

    private const string TIP = 'Inject a FormRequest (or Request typehint) and consume via $request->validated() / $request->safe() instead of the global helper.';

    private function message(string $callLabel): string
    {
        return sprintf(self::MESSAGE_PATTERN, $callLabel);
    }

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
            [$this->message("request('a')"), 13, self::TIP],
            [$this->message("request('b')"), 14, self::TIP],
        ]);
    }

    #[Test]
    public function falls_back_to_generic_label_for_dynamic_argument(): void
    {
        $this->analyse([__DIR__ . '/stubs/RequestHelperDynamicKeyStub.php'], [
            [$this->message('request(...)'), 9, self::TIP],
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
            [$this->message("request('a')"), 13, self::TIP],
            [$this->message("request('b')"), 14, self::TIP],
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
