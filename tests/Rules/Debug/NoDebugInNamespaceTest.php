<?php declare(strict_types=1);

namespace Rules\Debug;

use Hihaho\PhpstanRules\Rules\Debug\NoDebugInNamespaceRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<NoDebugInNamespaceRule>
 */
class NoDebugInNamespaceTest extends RuleTestCase
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
}
