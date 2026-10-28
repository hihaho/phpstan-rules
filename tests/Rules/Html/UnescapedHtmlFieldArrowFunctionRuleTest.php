<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Html;

use Hihaho\PhpstanRules\Rules\Html\HtmlFieldReturnSink;
use Hihaho\PhpstanRules\Rules\Html\HtmlSafetyJudge;
use Hihaho\PhpstanRules\Rules\Html\UnescapedHtmlFieldArrowFunctionRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<UnescapedHtmlFieldArrowFunctionRule>
 */
final class UnescapedHtmlFieldArrowFunctionRuleTest extends RuleTestCase
{
    #[Override]
    protected function getRule(): Rule
    {
        return new UnescapedHtmlFieldArrowFunctionRule(
            new HtmlFieldReturnSink(['App'], [], new HtmlSafetyJudge(), self::createReflectionProvider()),
        );
    }

    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/config.neon'];
    }

    /**
     * Below the flagged fields every arrow function is an exemption: e(), a
     * ->text() chain (as element and as toHtml()), an int, a backed enum, a
     * literal, a field without asHtml(), and an ignore comment with a reason.
     */
    #[Test]
    public function flags_unescaped_arrow_function_bodies(): void
    {
        $this->analyse([__DIR__ . '/stubs/nova-field-arrow-functions.php'], [
            [UnescapedHtmlFieldClosureRuleTest::MESSAGE, 16, UnescapedHtmlFieldClosureRuleTest::TIP],  // asHtml() before displayUsing()
            [UnescapedHtmlFieldClosureRuleTest::MESSAGE, 17, UnescapedHtmlFieldClosureRuleTest::TIP],  // make() callback in third position
            [UnescapedHtmlFieldClosureRuleTest::MESSAGE, 18, UnescapedHtmlFieldClosureRuleTest::TIP],  // make() callback in second position
            [UnescapedHtmlFieldClosureRuleTest::MESSAGE, 19, UnescapedHtmlFieldClosureRuleTest::TIP],  // only one side of ?? is a literal
        ]);
    }
}
