<?php declare(strict_types=1);

namespace Rules;

use Hihaho\PhpstanRules\Rules\NoDebugInBlade;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<NoDebugInBlade>
 */
class NoDebugInBladeTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoDebugInBlade();
    }

    #[Test]
    public function no_debug_statements_should_be_present_in_blade(): void
    {
        $this->analyse([__DIR__ . '/stubs/debug-in-view.blade.php'], [
            [
                'No debug statements should be present in blade files.',
                1,
            ],
        ]);
    }
}
