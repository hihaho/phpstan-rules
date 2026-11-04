<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Conventions;

use Hihaho\PhpstanRules\Rules\Conventions\PositionalFlagArgumentMethodCallRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<PositionalFlagArgumentMethodCallRule>
 */
final class PositionalFlagArgumentMultiVariantTest extends RuleTestCase
{
    #[Override]
    protected function getRule(): Rule
    {
        return new PositionalFlagArgumentMethodCallRule(firstPartyNamespaces: ['App']);
    }

    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/multi-variant-method.neon'];
    }

    #[Test]
    public function names_the_flag_after_the_variant_selected_from_the_arguments(): void
    {
        $this->fix(__DIR__ . '/stubs/FlagFixMultiVariantStub.php', __DIR__ . '/stubs/FlagFixMultiVariantStub.php.fixed');
    }
}
