<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Database;

use Hihaho\PhpstanRules\Rules\Database\ArrayDefinitionReader;
use Hihaho\PhpstanRules\Rules\Database\BlueprintChain;
use Hihaho\PhpstanRules\Rules\Database\BlueprintDefinitionResolver;
use Hihaho\PhpstanRules\Rules\Database\HelperCallSites;
use Hihaho\PhpstanRules\Rules\Database\PropertyArraySource;
use Hihaho\PhpstanRules\Rules\Database\RawAlterScanner;
use Hihaho\PhpstanRules\Rules\Database\SlowMigrationDdlRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * With no `outlierTables` configured the rule reports nothing, so a project that
 * installs this package inherits no opinion about which of its tables are too large
 * to alter in a deploy. Each project measures its own.
 *
 * @extends RuleTestCase<SlowMigrationDdlRule>
 */
final class SlowMigrationDdlRuleInertTest extends RuleTestCase
{
    #[Override]
    protected function getRule(): Rule
    {
        $chain = new BlueprintChain(self::createReflectionProvider());

        return new SlowMigrationDdlRule([], $chain, new RawAlterScanner([]), new BlueprintDefinitionResolver(new ArrayDefinitionReader(new PropertyArraySource()), new HelperCallSites()));
    }

    #[Test]
    public function reports_nothing_when_no_outlier_tables_are_configured(): void
    {
        $this->analyse([__DIR__ . '/stubs/incident-foreign-key.php'], []);
    }
}
