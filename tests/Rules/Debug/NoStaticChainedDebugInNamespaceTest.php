<?php declare(strict_types=1);

namespace Rules\Debug;

use Hihaho\PhpstanRules\Rules\Debug\StaticChainedNoDebugInNamespaceRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<StaticChainedNoDebugInNamespaceRule>
 */
final class NoStaticChainedDebugInNamespaceTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new StaticChainedNoDebugInNamespaceRule();
    }

    #[Test]
    public function should_not_contain_static_called_debug_statements(): void
    {
        $this->analyse([__DIR__ . '/stubs/StaticChainedDebugInAppNamespaceStub.php'], [
            ['No statically called debug statements should be present in the App namespace.', 11],
            ['No statically called debug statements should be present in the App namespace.', 12],
        ]);

        $this->analyse([__DIR__ . '/stubs/StaticChainedDebugInTestNamespaceStub.php'], [
            ['No statically called debug statements should be present in the Tests namespace.', 11],
            ['No statically called debug statements should be present in the Tests namespace.', 12],
        ]);
    }

    #[Test]
    public function should_have_correct_error_identifiers_for_tests_namespace(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/StaticChainedDebugInTestNamespaceStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.debug.noStaticChainedDebugInTests', $error->getIdentifier());
        }
    }

    #[Test]
    public function should_flag_all_six_static_chained_debug_statements(): void
    {
        $this->analyse([__DIR__ . '/stubs/AllStaticChainedDebugInAppNamespaceStub.php'], [
            ['No statically called debug statements should be present in the App namespace.', 11],
            ['No statically called debug statements should be present in the App namespace.', 16],
            ['No statically called debug statements should be present in the App namespace.', 21],
            ['No statically called debug statements should be present in the App namespace.', 26],
            ['No statically called debug statements should be present in the App namespace.', 31],
            ['No statically called debug statements should be present in the App namespace.', 36],
        ]);
    }
}
