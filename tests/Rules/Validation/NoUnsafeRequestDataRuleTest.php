<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Validation;

use Hihaho\PhpstanRules\Rules\Validation\NoUnsafeRequestDataRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<NoUnsafeRequestDataRule>
 */
final class NoUnsafeRequestDataRuleTest extends RuleTestCase
{
    private const string MESSAGE_PATTERN = 'Reading unvalidated request data via %s() is not allowed. Use a FormRequest, $request->validated(), or $request->safe().';

    private const string TIP = 'Inject a FormRequest subclass, or call $request->validated() / $request->safe() before reading input.';

    #[Override]
    protected function getRule(): Rule
    {
        return new NoUnsafeRequestDataRule(
            unsafeMethods: [
                'input', 'all', 'get', 'query', 'post', 'only', 'except', 'collect',
                'string', 'str', 'integer', 'boolean', 'float', 'json', 'keys',
                'fluent', 'array', 'date', 'enum', 'enums',
            ],
            namespaces: ['App'],
            excludeNamespaces: ['App\\Providers', 'App\\Http\\Responses'],
        );
    }

    #[Test]
    public function flags_unsafe_methods_on_illuminate_request_in_controller(): void
    {
        $this->analyse([__DIR__ . '/stubs/RequestInControllerStub.php'], [
            [sprintf(self::MESSAGE_PATTERN, 'input'), 15, self::TIP],
            [sprintf(self::MESSAGE_PATTERN, 'all'), 16, self::TIP],
            [sprintf(self::MESSAGE_PATTERN, 'get'), 17, self::TIP],
            [sprintf(self::MESSAGE_PATTERN, 'only'), 18, self::TIP],
        ]);
    }

    #[Test]
    public function flags_unsafe_methods_on_form_request_in_controller(): void
    {
        $this->analyse(
            [
                __DIR__ . '/stubs/SharedUserFormRequest.php',
                __DIR__ . '/stubs/FormRequestInControllerStub.php',
            ],
            [
                [sprintf(self::MESSAGE_PATTERN, 'all'), 15, self::TIP],
                [sprintf(self::MESSAGE_PATTERN, 'input'), 16, self::TIP],
            ]
        );
    }

    #[Test]
    public function does_not_flag_validated_access_patterns(): void
    {
        $this->analyse([__DIR__ . '/stubs/ValidatedAccessStub.php'], []);
    }

    #[Test]
    public function does_not_flag_code_outside_configured_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/VendorNamespaceStub.php'], []);
    }

    #[Test]
    public function does_not_flag_code_inside_excluded_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/ProvidersNamespaceStub.php'], []);
    }

    #[Test]
    public function does_not_flag_code_inside_http_responses_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/ResponsesNamespaceStub.php'], []);
    }

    #[Test]
    public function does_not_flag_raw_reads_inside_form_request_class(): void
    {
        $this->analyse([__DIR__ . '/stubs/FormRequestInternalStub.php'], []);
    }

    #[Test]
    public function does_not_flag_dynamic_method_calls(): void
    {
        $this->analyse([__DIR__ . '/stubs/DynamicMethodCallStub.php'], []);
    }

    #[Test]
    public function flags_union_type_receivers_when_any_member_is_a_request(): void
    {
        $this->analyse([__DIR__ . '/stubs/UnionReceiverStub.php'], [
            [sprintf(self::MESSAGE_PATTERN, 'input'), 19, self::TIP],
        ]);
    }

    #[Test]
    public function does_not_flag_raw_reads_inside_custom_base_form_request(): void
    {
        $this->analyse([__DIR__ . '/stubs/CustomBaseFormRequestStub.php'], []);
    }

    #[Test]
    public function flags_unsafe_methods_on_custom_base_form_request_in_controller(): void
    {
        $this->analyse(
            [
                __DIR__ . '/stubs/CustomBaseFormRequestStub.php',
                __DIR__ . '/stubs/CustomBaseFormRequestInControllerStub.php',
            ],
            [
                [sprintf(self::MESSAGE_PATTERN, 'all'), 15, self::TIP],
                [sprintf(self::MESSAGE_PATTERN, 'input'), 16, self::TIP],
            ]
        );
    }

    #[Test]
    public function flags_unsafe_methods_regardless_of_call_site_casing(): void
    {
        $this->analyse([__DIR__ . '/stubs/MixedCaseMethodStub.php'], [
            [sprintf(self::MESSAGE_PATTERN, 'INPUT'), 15, self::TIP],
            [sprintf(self::MESSAGE_PATTERN, 'All'), 16, self::TIP],
            [sprintf(self::MESSAGE_PATTERN, 'GeT'), 17, self::TIP],
        ]);
    }

    #[Test]
    public function flags_chained_request_helper(): void
    {
        $this->analyse([__DIR__ . '/stubs/RequestHelperChainStub.php'], [
            [sprintf(self::MESSAGE_PATTERN, 'input'), 13, self::TIP],
            [sprintf(self::MESSAGE_PATTERN, 'all'), 14, self::TIP],
        ]);
    }

    #[Test]
    public function error_uses_correct_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/RequestInControllerStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.validation.noUnsafeRequestData', $error->getIdentifier());
        }
    }
}
