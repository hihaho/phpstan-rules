<?php declare(strict_types=1);

namespace Rules\Debug;

use Hihaho\PhpstanRules\Rules\Debug\ChainedNoDebugInNamespaceRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<ChainedNoDebugInNamespaceRule>
 */
final class NoChainedDebugInNamespaceTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ChainedNoDebugInNamespaceRule();
    }

    #[Test]
    public function should_not_contain_chained_debug_statements(): void
    {
        $this->analyse([__DIR__ . '/stubs/ChainedDebugInAppNamespaceStub.php'], [
            ['No chained debug statements should be present in the App namespace.', 9],
            ['No chained debug statements should be present in the App namespace.', 10],
        ]);

        $this->analyse([__DIR__ . '/stubs/ChainedDebugInTestNamespaceStub.php'], [
            ['No chained debug statements should be present in the Tests namespace.', 9],
            ['No chained debug statements should be present in the Tests namespace.', 10],
        ]);
    }

    #[Test]
    public function should_flag_all_six_chained_debug_statements(): void
    {
        $this->analyse([__DIR__ . '/stubs/AllChainedDebugInAppNamespaceStub.php'], [
            ['No chained debug statements should be present in the App namespace.', 9],
            ['No chained debug statements should be present in the App namespace.', 14],
            ['No chained debug statements should be present in the App namespace.', 19],
            ['No chained debug statements should be present in the App namespace.', 24],
            ['No chained debug statements should be present in the App namespace.', 29],
            ['No chained debug statements should be present in the App namespace.', 34],
        ]);
    }
}
