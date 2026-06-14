<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules;

use Hihaho\PhpstanRules\Rules\CombinedFuncCallRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Covers the registered CombinedFuncCallRule by mirroring the cases of its three
 * merged twins (debug-function, invade, unsafe-request-helper) against the same
 * stub fixtures.
 *
 * @extends RuleTestCase<CombinedFuncCallRule>
 */
final class CombinedFuncCallRuleTest extends RuleTestCase
{
    private const string HELPER_PATTERN = 'Reading unvalidated request data via %s is not allowed. Use a FormRequest, $request->validated(), or $request->safe().';

    private const string HELPER_TIP = 'Inject a FormRequest (or Request typehint) and consume via $request->validated() / $request->safe() instead of the global helper.';

    #[Override]
    protected function getRule(): Rule
    {
        return new CombinedFuncCallRule(
            namespaces: ['App'],
            excludeNamespaces: ['App\\Providers', 'App\\Http\\Responses'],
            reflectionProvider: self::createReflectionProvider(),
        );
    }

    private function helperMessage(string $callLabel): string
    {
        return sprintf(self::HELPER_PATTERN, $callLabel);
    }

    // ----- debug-function concern (mirrors NoDebugInNamespaceTest) -----

    #[Test]
    public function no_debug_statements_should_be_present_in_application_code(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/DebugInAppNamespaceStub.php'], [
            ['No debug statements should be present in the App namespace.', 12],
            ['No debug statements should be present in the App namespace.', 13],
            ['No debug statements should be present in the App namespace.', 14],
        ]);
    }

    #[Test]
    public function no_debug_statements_should_be_present_in_test_code(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/DebugInTestNamespaceStub.php'], [
            ['No debug statements should be present in the Tests namespace.', 12],
            ['No debug statements should be present in the Tests namespace.', 13],
            ['No debug statements should be present in the Tests namespace.', 14],
        ]);
    }

    #[Test]
    public function should_not_flag_debug_statements_outside_app_and_tests_namespaces(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/DebugInVendorNamespaceStub.php'], []);
    }

    #[Test]
    public function should_not_flag_debug_statements_in_global_namespace(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/DebugInGlobalNamespaceStub.php'], []);
    }

    #[Test]
    public function should_flag_all_six_debug_statements(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/AllDebugInAppNamespaceStub.php'], [
            ['No debug statements should be present in the App namespace.', 9],
            ['No debug statements should be present in the App namespace.', 14],
            ['No debug statements should be present in the App namespace.', 19],
            ['No debug statements should be present in the App namespace.', 24],
            ['No debug statements should be present in the App namespace.', 29],
            ['No debug statements should be present in the App namespace.', 34],
        ]);
    }

    #[Test]
    public function should_not_flag_dynamic_function_call(): void
    {
        $this->analyse([__DIR__ . '/Debug/stubs/DynamicFuncCallInAppNamespaceStub.php'], []);
    }

    #[Test]
    public function should_have_correct_debug_identifier_in_app(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Debug/stubs/DebugInAppNamespaceStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.debug.noDebugInApp', $error->getIdentifier());
        }
    }

    #[Test]
    public function should_have_correct_debug_identifier_in_tests(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Debug/stubs/DebugInTestNamespaceStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.debug.noDebugInTests', $error->getIdentifier());
        }
    }

    // ----- invade concern (mirrors NoInvadeInAppCodeTest) -----

    #[Test]
    public function flags_invade_in_app_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/InvadeInAppNamespace.php'], [
            ['Usage of method `invade` is not allowed in the App namespace.', 12],
        ]);
    }

    #[Test]
    public function ignores_invade_in_test_fakes(): void
    {
        $this->analyse([__DIR__ . '/stubs/InvadeTestFake.php'], []);
    }

    #[Test]
    public function flags_livewire_invade(): void
    {
        $this->analyse([__DIR__ . '/stubs/UseSpatieInvadeInsteadOfLivewire.php'], [
            ['Usage of `\Livewire\invade` is disallowed, please use the global `invade` from spatie/invade.', 15],
        ]);
    }

    #[Test]
    public function should_not_match_namespaces_starting_with_app(): void
    {
        $this->analyse([__DIR__ . '/stubs/InvadeInApplicationNamespace.php'], []);
    }

    #[Test]
    public function should_flag_invade_in_app_sub_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/InvadeInAppSubNamespace.php'], [
            ['Usage of method `invade` is not allowed in the App namespace.', 12],
        ]);
    }

    #[Test]
    public function should_not_flag_invade_in_global_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/InvadeInGlobalNamespace.php'], []);
    }

    #[Test]
    public function should_not_flag_dynamic_invade_call(): void
    {
        $this->analyse([__DIR__ . '/stubs/DynamicInvadeCallInAppNamespace.php'], []);
    }

    #[Test]
    public function should_flag_livewire_invade_regardless_of_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/LivewireInvadeInVendorNamespace.php'], [
            ['Usage of `\Livewire\invade` is disallowed, please use the global `invade` from spatie/invade.', 14],
        ]);
    }

    #[Test]
    public function invade_uses_correct_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/InvadeInAppNamespace.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.generic.noInvadeInAppCode', $error->getIdentifier());
        }
    }

    #[Test]
    public function livewire_invade_uses_correct_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/UseSpatieInvadeInsteadOfLivewire.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.generic.disallowedUsageOfLivewireInvade', $error->getIdentifier());
        }
    }

    // ----- unsafe-request-helper concern (mirrors NoUnsafeRequestHelperRuleTest) -----

    #[Test]
    public function flags_request_helper_with_argument(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/RequestHelperWithArgStub.php'], [
            [$this->helperMessage("request('a')"), 13, self::HELPER_TIP],
            [$this->helperMessage("request('b')"), 14, self::HELPER_TIP],
        ]);
    }

    #[Test]
    public function falls_back_to_generic_label_for_dynamic_argument(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/RequestHelperDynamicKeyStub.php'], [
            [$this->helperMessage('request(...)'), 9, self::HELPER_TIP],
        ]);
    }

    #[Test]
    public function does_not_flag_zero_arg_request_helper(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/RequestHelperNoArgStub.php'], []);
    }

    #[Test]
    public function does_not_flag_request_helper_outside_configured_namespace(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/RequestHelperOutsideNamespaceStub.php'], []);
    }

    #[Test]
    public function does_not_flag_request_helper_inside_excluded_namespace(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/ProvidersNamespaceStub.php'], []);
    }

    #[Test]
    public function flags_fully_qualified_and_mixed_case_request_helper(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/FullyQualifiedRequestHelperStub.php'], [
            [$this->helperMessage("request('a')"), 13, self::HELPER_TIP],
            [$this->helperMessage("request('b')"), 14, self::HELPER_TIP],
        ]);
    }

    #[Test]
    public function flags_aliased_request_helper_import(): void
    {
        $this->analyse([__DIR__ . '/Validation/stubs/AliasedRequestHelperStub.php'], [
            [$this->helperMessage("request('a')"), 15, self::HELPER_TIP],
            [$this->helperMessage("request('b')"), 16, self::HELPER_TIP],
        ]);
    }

    #[Test]
    public function request_helper_uses_correct_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Validation/stubs/RequestHelperWithArgStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.validation.noUnsafeRequestHelper', $error->getIdentifier());
        }
    }
}
