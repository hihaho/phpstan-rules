<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Html;

use Hihaho\PhpstanRules\Rules\CombinedStaticCallRule;
use Hihaho\PhpstanRules\Rules\Html\HtmlBuilderContentSink;
use Hihaho\PhpstanRules\Rules\Html\HtmlSafetyJudge;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The `Html::` facade form of the builder-content check, as
 * CombinedStaticCallRule runs it.
 *
 * @extends RuleTestCase<CombinedStaticCallRule>
 */
final class HtmlBuilderContentFacadeTest extends RuleTestCase
{
    #[Override]
    protected function getRule(): Rule
    {
        return new CombinedStaticCallRule(
            reflectionProvider: self::createReflectionProvider(),
            unsafeMethods: [],
            namespaces: [],
            excludeNamespaces: [],
            firstPartyNamespaces: [],
            htmlSink: new HtmlBuilderContentSink(['App'], [], new HtmlSafetyJudge()),
        );
    }

    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/config.neon'];
    }

    #[Test]
    public function flags_unescaped_content_passed_to_the_facade(): void
    {
        $this->analyse([__DIR__ . '/stubs/builder-content.php'], [
            ['span() renders its content without escaping it, and this value can contain HTML.', 25, 'Pass the value through ->text() or e() first. If it is trusted HTML, add @phpstan-ignore hihaho.html.unescapedBuilderContent (reason).'],
        ]);
    }
}
