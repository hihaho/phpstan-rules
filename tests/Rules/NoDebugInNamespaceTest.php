<?php declare(strict_types=1);

namespace Rules;

use Hihaho\PhpstanRules\Rules\NoDebugInNamespace;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<NoDebugInNamespace>
 */
class NoDebugInNamespaceTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoDebugInNamespace();
    }

    #[Test]
    public function no_debug_statements_should_be_present_in_application_code(): void
    {
        $this->analyse([__DIR__ . '/stubs/DebugInAppNamespace.php'], [
            [
                'No debug statements should be present in the app namespace.',
                12,
            ],
            [
                'No debug statements should be present in the app namespace.',
                13,
            ],
            [
                'No debug statements should be present in the app namespace.',
                14,
            ],
        ]);
    }

    #[Test]
    public function no_debug_statements_should_be_present_in_test_code(): void
    {
        $this->analyse([__DIR__ . '/stubs/DebugInTestNamespace.php'], [
            [
                'No debug statements should be present in the test namespace.',
                12,
            ],
            [
                'No debug statements should be present in the test namespace.',
                13,
            ],
            [
                'No debug statements should be present in the test namespace.',
                14,
            ],
        ]);
    }
}
