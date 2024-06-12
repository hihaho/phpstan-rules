<?php declare(strict_types=1);

namespace Rules\Debug;

use Hihaho\PhpstanRules\Rules\Debug\NoDebugInBladeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<NoDebugInBladeRule>
 */
class NoDebugInBladeTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoDebugInBladeRule();
    }

    #[Test]
    public function no_debug_statements_should_be_present_in_blade(): void
    {
        $this->analyse([__DIR__ . '/stubs/debug-in-view-stub.blade.php'], [
            [
                'No debug directives should be present in blade files.',
                1,
            ],
        ]);
    }
}
