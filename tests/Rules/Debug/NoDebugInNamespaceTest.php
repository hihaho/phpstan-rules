<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Debug;

use Hihaho\PhpstanRules\Rules\Debug\NoDebugInNamespaceRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<NoDebugInNamespaceRule>
 */
final class NoDebugInNamespaceTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoDebugInNamespaceRule();
    }

    #[Test]
    public function no_debug_statements_should_be_present_in_application_code(): void
    {
        $this->analyse([__DIR__ . '/stubs/DebugInAppNamespaceStub.php'], [
            [
                'No debug statements should be present in the App namespace.',
                12,
            ],
            [
                'No debug statements should be present in the App namespace.',
                13,
            ],
            [
                'No debug statements should be present in the App namespace.',
                14,
            ],
        ]);
    }

    #[Test]
    public function no_debug_statements_should_be_present_in_test_code(): void
    {
        $this->analyse([__DIR__ . '/stubs/DebugInTestNamespaceStub.php'], [
            [
                'No debug statements should be present in the Tests namespace.',
                12,
            ],
            [
                'No debug statements should be present in the Tests namespace.',
                13,
            ],
            [
                'No debug statements should be present in the Tests namespace.',
                14,
            ],
        ]);
    }

    #[Test]
    public function should_not_flag_debug_statements_outside_app_and_tests_namespaces(): void
    {
        $this->analyse([__DIR__ . '/stubs/DebugInVendorNamespaceStub.php'], []);
    }

    #[Test]
    public function should_not_flag_debug_statements_in_global_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/DebugInGlobalNamespaceStub.php'], []);
    }

    #[Test]
    public function should_flag_all_six_debug_statements(): void
    {
        $this->analyse([__DIR__ . '/stubs/AllDebugInAppNamespaceStub.php'], [
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
        // Branch: `$node->name` is not `Node\Name` (dynamic call via variable).
        $this->analyse([__DIR__ . '/stubs/DynamicFuncCallInAppNamespaceStub.php'], []);
    }

    #[Test]
    public function should_have_correct_error_identifier_in_app(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/DebugInAppNamespaceStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.debug.noDebugInApp', $error->getIdentifier());
        }
    }

    #[Test]
    public function should_have_correct_error_identifier_in_tests(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/DebugInTestNamespaceStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.debug.noDebugInTests', $error->getIdentifier());
        }
    }
}
