<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Validation;

use Hihaho\PhpstanRules\Rules\Validation\UnvalidatedFormRequestFieldRule;
use Override;
use PHPStan\Parser\Parser;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<UnvalidatedFormRequestFieldRule>
 */
final class UnvalidatedFormRequestFieldRuleTest extends RuleTestCase
{
    private const string MESSAGE_PATTERN = "Reading '%s' via %s() but the FormRequest's rules() never validates it.";

    #[Override]
    protected function getRule(): Rule
    {
        /** @var Parser $parser */
        $parser = self::getContainer()->getService('defaultAnalysisParser');

        return new UnvalidatedFormRequestFieldRule(
            fieldAccessors: ['input', 'get', 'query', 'post', 'string', 'str', 'integer', 'boolean', 'float', 'json', 'array', 'collect', 'date', 'enum', 'enums', 'file'],
            namespaces: ['App'],
            excludeNamespaces: ['App\\Providers', 'App\\Http\\Responses'],
            parser: $parser,
        );
    }

    private function tip(string $key): string
    {
        return "Add '{$key}' to rules(), or if it is intentionally unvalidated suppress with @phpstan-ignore-next-line hihaho.validation.unvalidatedFormRequestField.";
    }

    #[Test]
    public function flags_a_field_read_that_rules_never_validates(): void
    {
        $this->analyse([__DIR__ . '/stubs/UnvalidatedFormRequestFieldStub.php'], [
            [sprintf(self::MESSAGE_PATTERN, 'submit_redirect', 'boolean'), 21, $this->tip('submit_redirect')],
        ]);
    }

    #[Test]
    public function flags_a_generic_get_reader_that_would_otherwise_bypass_both_rules(): void
    {
        $this->analyse([__DIR__ . '/stubs/GetAccessorFormRequestStub.php'], [
            [sprintf(self::MESSAGE_PATTERN, 'status', 'get'), 21, $this->tip('status')],
        ]);
    }

    #[Test]
    public function flags_every_unvalidated_accessor(): void
    {
        $this->analyse([__DIR__ . '/stubs/MultiUnvalidatedFormRequestFieldStub.php'], [
            [sprintf(self::MESSAGE_PATTERN, 'title', 'string'), 21, $this->tip('title')],
            [sprintf(self::MESSAGE_PATTERN, 'count', 'integer'), 22, $this->tip('count')],
            [sprintf(self::MESSAGE_PATTERN, 'avatar', 'file'), 23, $this->tip('avatar')],
        ]);
    }

    #[Test]
    public function does_not_flag_validated_fields(): void
    {
        $this->analyse([__DIR__ . '/stubs/AllValidatedFormRequestStub.php'], []);
    }

    #[Test]
    public function does_not_flag_a_field_whose_root_segment_is_validated(): void
    {
        $this->analyse([__DIR__ . '/stubs/NestedRuleKeyFormRequestStub.php'], []);
    }

    #[Test]
    public function flags_every_read_when_rules_is_empty(): void
    {
        $this->analyse([__DIR__ . '/stubs/EmptyRulesFormRequestStub.php'], [
            [sprintf(self::MESSAGE_PATTERN, 'name', 'string'), 19, $this->tip('name')],
        ]);
    }

    #[Test]
    public function does_not_flag_when_the_class_declares_no_rules_method(): void
    {
        $this->analyse([__DIR__ . '/stubs/NoRulesMethodFormRequestStub.php'], []);
    }

    #[Test]
    public function does_not_flag_when_rules_returns_conditionally(): void
    {
        $this->analyse([__DIR__ . '/stubs/ConditionalRulesFormRequestStub.php'], []);
    }

    #[Test]
    public function resolves_rules_inherited_from_a_base_class(): void
    {
        $this->analyse(
            [
                __DIR__ . '/stubs/SharedRulesBaseFormRequest.php',
                __DIR__ . '/stubs/ChildUsingInheritedRulesFormRequest.php',
            ],
            [
                [sprintf(self::MESSAGE_PATTERN, 'not_shared', 'string'), 10, $this->tip('not_shared')],
            ]
        );
    }

    #[Test]
    public function does_not_flag_when_rules_is_an_opaque_array_merge(): void
    {
        $this->analyse([__DIR__ . '/stubs/OpaqueMergeFormRequestStub.php'], []);
    }

    #[Test]
    public function does_not_flag_when_rules_returns_a_variable(): void
    {
        $this->analyse([__DIR__ . '/stubs/OpaqueVariableReturnFormRequestStub.php'], []);
    }

    #[Test]
    public function does_not_flag_when_rules_uses_a_spread(): void
    {
        $this->analyse([__DIR__ . '/stubs/SpreadRulesFormRequestStub.php'], []);
    }

    #[Test]
    public function does_not_flag_when_class_defines_prepare_for_validation(): void
    {
        $this->analyse([__DIR__ . '/stubs/PrepareForValidationFormRequestStub.php'], []);
    }

    #[Test]
    public function flags_an_unvalidated_read_from_a_trait_method(): void
    {
        $this->analyse(
            [
                __DIR__ . '/stubs/Concerns/ReadsUnvalidatedFieldTrait.php',
                __DIR__ . '/stubs/TraitBackedFormRequestStub.php',
            ],
            [
                [sprintf(self::MESSAGE_PATTERN, 'from_trait', 'string'), 9, $this->tip('from_trait')],
            ]
        );
    }

    #[Test]
    public function does_not_flag_when_an_opaque_method_is_inherited_from_a_user_base(): void
    {
        $this->analyse(
            [
                __DIR__ . '/stubs/InheritedOpaqueBaseFormRequest.php',
                __DIR__ . '/stubs/ChildOfOpaqueBaseFormRequest.php',
            ],
            []
        );
    }

    #[Test]
    public function does_not_flag_a_dynamic_field_key(): void
    {
        $this->analyse([__DIR__ . '/stubs/DynamicFieldKeyFormRequestStub.php'], []);
    }

    #[Test]
    public function does_not_flag_an_identically_named_method_on_a_non_form_request(): void
    {
        $this->analyse([__DIR__ . '/stubs/NonFormRequestFieldStub.php'], []);
    }

    #[Test]
    public function does_not_flag_a_form_request_outside_the_configured_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/OutsideNamespaceFormRequestStub.php'], []);
    }

    #[Test]
    public function error_uses_correct_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/UnvalidatedFormRequestFieldStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.validation.unvalidatedFormRequestField', $error->getIdentifier());
        }
    }
}
