<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules;

use Hihaho\PhpstanRules\Rules\CombinedMethodCallRule;
use Override;
use PHPStan\Parser\Parser;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers the registered CombinedMethodCallRule by mirroring the cases of its
 * four merged twins (chained-debug, unsafe-request-data, unvalidated-field,
 * positional-flag) against the same stub fixtures. Each stub exercises a single
 * concern, so the Combined rule produces the same errors the twin does.
 *
 * @extends RuleTestCase<CombinedMethodCallRule>
 */
final class CombinedMethodCallRuleTest extends RuleTestCase
{
    private const string UNSAFE_DATA_PATTERN = 'Reading unvalidated request data via %s() is not allowed. Use a FormRequest, $request->validated(), or $request->safe().';

    private const string UNSAFE_DATA_TIP = "Use \$request->validated() or \$request->safe() to consume validated data. For Stringable/int/bool accessors, \$request->safe()->string('key') mirrors \$request->string('key') against validated input.";

    private const string UNVALIDATED_PATTERN = "Reading '%s' via %s() but the FormRequest's rules() never validates it.";

    #[Override]
    protected function getRule(): Rule
    {
        /** @var Parser $parser */
        $parser = self::getContainer()->getService('defaultAnalysisParser');

        return new CombinedMethodCallRule(
            unsafeMethods: [
                'input', 'all', 'get', 'query', 'post', 'only', 'except', 'collect',
                'string', 'str', 'integer', 'boolean', 'float', 'json', 'keys',
                'fluent', 'array', 'date', 'enum', 'enums', 'file', 'allFiles',
            ],
            fieldAccessors: ['input', 'get', 'query', 'post', 'string', 'str', 'integer', 'boolean', 'float', 'json', 'array', 'collect', 'date', 'enum', 'enums', 'file'],
            namespaces: ['App'],
            excludeNamespaces: ['App\\Providers', 'App\\Http\\Responses'],
            parser: $parser,
            firstPartyNamespaces: ['App', 'Database\\Factories', 'Tests'],
        );
    }

    private function unvalidatedTip(string $key): string
    {
        return "Add '{$key}' to rules(), or if it is intentionally unvalidated suppress with @phpstan-ignore-next-line hihaho.validation.unvalidatedFormRequestField.";
    }

    private function flagMessage(string $param): string
    {
        return "Pass a named argument ({$param}: ...) for the bool/null flag — it is opaque positionally.";
    }

    private function flagTip(): string
    {
        return 'Name the flag at the call site so its meaning is visible: instead of foo(true), write foo(enabled: true).';
    }

    // ----- chained-debug concern (mirrors NoChainedDebugInNamespaceTest) -----

    #[Test]
    public function should_not_contain_chained_debug_statements_in_app_namespace(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/ChainedDebugInAppNamespaceStub.php'], [
            ['No chained debug statements should be present in the App namespace.', 9],
            ['No chained debug statements should be present in the App namespace.', 10],
        ]);
    }

    #[Test]
    public function should_not_contain_chained_debug_statements_in_tests_namespace(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/ChainedDebugInTestNamespaceStub.php'], [
            ['No chained debug statements should be present in the Tests namespace.', 9],
            ['No chained debug statements should be present in the Tests namespace.', 10],
        ]);
    }

    #[Test]
    public function should_not_flag_chained_debug_statements_outside_app_and_tests_namespaces(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/ChainedDebugInVendorNamespaceStub.php'], []);
    }

    #[Test]
    public function should_not_flag_user_classes_with_their_own_dump_method(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/CustomDumpableInAppNamespaceStub.php'], []);
    }

    #[Test]
    public function should_not_flag_when_receiver_type_is_unknown(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/UnknownReceiverChainedDumpStub.php'], []);
    }

    #[Test]
    public function should_not_flag_dynamic_method_name(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/DynamicMethodCallInAppNamespaceStub.php'], []);
    }

    #[Test]
    public function should_flag_union_type_receiver_when_any_member_is_laravel(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/UnionReceiverChainedDumpStub.php'], [
            ['No chained debug statements should be present in the App namespace.', 29],
        ]);
    }

    #[Test]
    public function should_flag_all_method_debug_statements_declared_by_illuminate(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/AllChainedDebugInAppNamespaceStub.php'], [
            ['No chained debug statements should be present in the App namespace.', 9],
            ['No chained debug statements should be present in the App namespace.', 14],
        ]);
    }

    #[Test]
    public function debug_uses_correct_error_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Debug/stubs/ChainedDebugInAppNamespaceStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.debug.noChainedDebugInApp', $error->getIdentifier());
        }
    }

    // ----- unsafe-request-data concern (mirrors NoUnsafeRequestDataRuleTest) -----

    #[Test]
    public function flags_unsafe_methods_on_illuminate_request_in_controller(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/RequestInControllerStub.php'], [
            [sprintf(self::UNSAFE_DATA_PATTERN, 'input'), 15, self::UNSAFE_DATA_TIP],
            [sprintf(self::UNSAFE_DATA_PATTERN, 'all'), 16, self::UNSAFE_DATA_TIP],
            [sprintf(self::UNSAFE_DATA_PATTERN, 'get'), 17, self::UNSAFE_DATA_TIP],
            [sprintf(self::UNSAFE_DATA_PATTERN, 'only'), 18, self::UNSAFE_DATA_TIP],
        ]);
    }

    #[Test]
    public function flags_unsafe_methods_on_form_request_in_controller(): void
    {
        $this->analyse(
            [
                __DIR__ . '/Validation/stubs/SharedUserFormRequest.php',
                __DIR__ . '/Validation/stubs/FormRequestInControllerStub.php',
            ],
            [
                [sprintf(self::UNSAFE_DATA_PATTERN, 'all'), 15, self::UNSAFE_DATA_TIP],
                [sprintf(self::UNSAFE_DATA_PATTERN, 'input'), 16, self::UNSAFE_DATA_TIP],
            ]
        );
    }

    #[Test]
    public function does_not_flag_validated_access_patterns(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/ValidatedAccessStub.php'], []);
    }

    #[Test]
    public function does_not_flag_code_outside_configured_namespace(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/VendorNamespaceStub.php'], []);
    }

    #[Test]
    public function does_not_flag_code_inside_excluded_namespace(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/ProvidersNamespaceStub.php'], []);
    }

    #[Test]
    public function does_not_flag_code_inside_http_responses_namespace(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/ResponsesNamespaceStub.php'], []);
    }

    #[Test]
    public function does_not_flag_raw_reads_inside_form_request_class(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/FormRequestInternalStub.php'], []);
    }

    #[Test]
    public function does_not_flag_dynamic_method_calls(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/DynamicMethodCallStub.php'], []);
    }

    #[Test]
    public function flags_union_type_receivers_when_any_member_is_a_request(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/UnionReceiverStub.php'], [
            [sprintf(self::UNSAFE_DATA_PATTERN, 'input'), 19, self::UNSAFE_DATA_TIP],
        ]);
    }

    #[Test]
    public function does_not_flag_raw_reads_inside_custom_base_form_request(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/CustomBaseFormRequestStub.php'], []);
    }

    #[Test]
    public function flags_unsafe_methods_on_custom_base_form_request_in_controller(): void
    {
        $this->analyse(
            [
                __DIR__ . '/Validation/stubs/CustomBaseFormRequestStub.php',
                __DIR__ . '/Validation/stubs/CustomBaseFormRequestInControllerStub.php',
            ],
            [
                [sprintf(self::UNSAFE_DATA_PATTERN, 'all'), 15, self::UNSAFE_DATA_TIP],
                [sprintf(self::UNSAFE_DATA_PATTERN, 'input'), 16, self::UNSAFE_DATA_TIP],
            ]
        );
    }

    #[Test]
    public function flags_file_upload_readers(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/FileUploadReadersStub.php'], [
            [sprintf(self::UNSAFE_DATA_PATTERN, 'file'), 15, self::UNSAFE_DATA_TIP],
            [sprintf(self::UNSAFE_DATA_PATTERN, 'allFiles'), 16, self::UNSAFE_DATA_TIP],
        ]);
    }

    #[Test]
    public function flags_receiver_typed_via_docblock(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/DocblockTypedReceiverStub.php'], [
            [sprintf(self::UNSAFE_DATA_PATTERN, 'input'), 16, self::UNSAFE_DATA_TIP],
        ]);
    }

    #[Test]
    public function flags_unsafe_methods_regardless_of_call_site_casing(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/MixedCaseMethodStub.php'], [
            [sprintf(self::UNSAFE_DATA_PATTERN, 'INPUT'), 15, self::UNSAFE_DATA_TIP],
            [sprintf(self::UNSAFE_DATA_PATTERN, 'All'), 16, self::UNSAFE_DATA_TIP],
            [sprintf(self::UNSAFE_DATA_PATTERN, 'GeT'), 17, self::UNSAFE_DATA_TIP],
        ]);
    }

    #[Test]
    public function flags_chained_request_helper(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/RequestHelperChainStub.php'], [
            [sprintf(self::UNSAFE_DATA_PATTERN, 'input'), 13, self::UNSAFE_DATA_TIP],
            [sprintf(self::UNSAFE_DATA_PATTERN, 'all'), 14, self::UNSAFE_DATA_TIP],
        ]);
    }

    #[Test]
    public function unsafe_data_uses_correct_error_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Validation/stubs/RequestInControllerStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.validation.noUnsafeRequestData', $error->getIdentifier());
        }
    }

    // ----- unvalidated-field concern (mirrors UnvalidatedFormRequestFieldRuleTest) -----

    #[Test]
    public function flags_a_field_read_that_rules_never_validates(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/UnvalidatedFormRequestFieldStub.php'], [
            [sprintf(self::UNVALIDATED_PATTERN, 'submit_redirect', 'boolean'), 21, $this->unvalidatedTip('submit_redirect')],
        ]);
    }

    #[Test]
    public function flags_a_generic_get_reader_that_would_otherwise_bypass_both_rules(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/GetAccessorFormRequestStub.php'], [
            [sprintf(self::UNVALIDATED_PATTERN, 'status', 'get'), 21, $this->unvalidatedTip('status')],
        ]);
    }

    #[Test]
    public function flags_every_unvalidated_accessor(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/MultiUnvalidatedFormRequestFieldStub.php'], [
            [sprintf(self::UNVALIDATED_PATTERN, 'title', 'string'), 21, $this->unvalidatedTip('title')],
            [sprintf(self::UNVALIDATED_PATTERN, 'count', 'integer'), 22, $this->unvalidatedTip('count')],
            [sprintf(self::UNVALIDATED_PATTERN, 'avatar', 'file'), 23, $this->unvalidatedTip('avatar')],
        ]);
    }

    #[Test]
    public function does_not_flag_validated_fields(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/AllValidatedFormRequestStub.php'], []);
    }

    #[Test]
    public function does_not_flag_a_field_whose_root_segment_is_validated(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/NestedRuleKeyFormRequestStub.php'], []);
    }

    #[Test]
    public function flags_every_read_when_rules_is_empty(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/EmptyRulesFormRequestStub.php'], [
            [sprintf(self::UNVALIDATED_PATTERN, 'name', 'string'), 19, $this->unvalidatedTip('name')],
        ]);
    }

    #[Test]
    public function does_not_flag_when_the_class_declares_no_rules_method(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/NoRulesMethodFormRequestStub.php'], []);
    }

    #[Test]
    public function does_not_flag_when_rules_returns_conditionally(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/ConditionalRulesFormRequestStub.php'], []);
    }

    #[Test]
    public function resolves_rules_inherited_from_a_base_class(): void
    {
        $this->analyse(
            [
                __DIR__ . '/Validation/stubs/SharedRulesBaseFormRequest.php',
                __DIR__ . '/Validation/stubs/ChildUsingInheritedRulesFormRequest.php',
            ],
            [
                [sprintf(self::UNVALIDATED_PATTERN, 'not_shared', 'string'), 10, $this->unvalidatedTip('not_shared')],
            ]
        );
    }

    #[Test]
    public function does_not_flag_when_rules_is_an_opaque_array_merge(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/OpaqueMergeFormRequestStub.php'], []);
    }

    #[Test]
    public function does_not_flag_when_rules_returns_a_variable(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/OpaqueVariableReturnFormRequestStub.php'], []);
    }

    #[Test]
    public function does_not_flag_when_rules_uses_a_spread(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/SpreadRulesFormRequestStub.php'], []);
    }

    #[Test]
    public function does_not_flag_when_class_defines_prepare_for_validation(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/PrepareForValidationFormRequestStub.php'], []);
    }

    #[Test]
    public function flags_an_unvalidated_read_from_a_trait_method(): void
    {
        $this->analyse(
            [
                __DIR__ . '/Validation/stubs/Concerns/ReadsUnvalidatedFieldTrait.php',
                __DIR__ . '/Validation/stubs/TraitBackedFormRequestStub.php',
            ],
            [
                [sprintf(self::UNVALIDATED_PATTERN, 'from_trait', 'string'), 9, $this->unvalidatedTip('from_trait')],
            ]
        );
    }

    #[Test]
    public function does_not_flag_when_an_opaque_method_is_inherited_from_a_user_base(): void
    {
        $this->analyse(
            [
                __DIR__ . '/Validation/stubs/InheritedOpaqueBaseFormRequest.php',
                __DIR__ . '/Validation/stubs/ChildOfOpaqueBaseFormRequest.php',
            ],
            []
        );
    }

    #[Test]
    public function does_not_flag_a_dynamic_field_key(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/DynamicFieldKeyFormRequestStub.php'], []);
    }

    #[Test]
    public function does_not_flag_an_identically_named_method_on_a_non_form_request(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/NonFormRequestFieldStub.php'], []);
    }

    #[Test]
    public function does_not_flag_a_form_request_outside_the_configured_namespace(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/OutsideNamespaceFormRequestStub.php'], []);
    }

    #[Test]
    public function unvalidated_field_uses_correct_error_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Validation/stubs/UnvalidatedFormRequestFieldStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.validation.unvalidatedFormRequestField', $error->getIdentifier());
        }
    }

    // ----- positional-flag concern (mirrors PositionalFlagArgumentMethodCallRuleTest) -----

    #[Test]
    public function flags_a_trailing_bool_or_null_flag_on_a_first_party_method(): void
    {
        $this->analyse([__DIR__ . '/Conventions/stubs/FlagMethodCallStub.php'], [
            [$this->flagMessage('active'), 22, $this->flagTip()],
            [$this->flagMessage('option'), 23, $this->flagTip()],
        ]);
    }

    #[Test]
    public function does_not_flag_a_call_on_a_non_first_party_class(): void
    {
        $this->analyse([__DIR__ . '/Conventions/stubs/NonFirstPartyMethodCallStub.php'], []);
    }

    #[Test]
    public function flags_a_bare_null_on_any_named_parameter_not_only_bool(): void
    {
        $this->analyse([__DIR__ . '/Conventions/stubs/NonBoolNullArgStub.php'], [
            [$this->flagMessage('id'), 18, $this->flagTip()],
            [$this->flagMessage('name'), 19, $this->flagTip()],
        ]);
    }

    #[Test]
    public function does_not_flag_a_vendor_method_inherited_by_a_first_party_class(): void
    {
        $this->analyse([__DIR__ . '/Conventions/stubs/InheritedVendorMethodStub.php'], []);
    }

    #[Test]
    public function positional_flag_uses_correct_error_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Conventions/stubs/FlagMethodCallStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.conventions.positionalFlagArgument', $error->getIdentifier());
        }
    }
}
