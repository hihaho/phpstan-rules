<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Html;

use Hihaho\PhpstanRules\Rules\CombinedMethodCallRule;
use Hihaho\PhpstanRules\Rules\Html\HtmlBuilderContentSink;
use Hihaho\PhpstanRules\Rules\Html\HtmlSafetyJudge;
use Override;
use PHPStan\Parser\Parser;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The builder-content check as CombinedMethodCallRule runs it, with every other
 * check in that rule switched off.
 *
 * @extends RuleTestCase<CombinedMethodCallRule>
 */
final class HtmlBuilderContentMethodCallTest extends RuleTestCase
{
    /** @var list<string> */
    private array $namespaces = ['App'];

    #[Override]
    protected function getRule(): Rule
    {
        /** @var Parser $parser */
        $parser = self::getContainer()->getService('defaultAnalysisParser');

        return new CombinedMethodCallRule(
            unsafeMethods: [],
            fieldAccessors: [],
            namespaces: [],
            excludeNamespaces: [],
            parser: $parser,
            firstPartyNamespaces: [],
            htmlSink: new HtmlBuilderContentSink($this->namespaces, ['App\Excluded'], new HtmlSafetyJudge()),
        );
    }

    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/config.neon'];
    }

    /**
     * Every call in the fixture's safe() method is an exemption: a literal, a
     * number, a bool, a backed enum, e()/htmlspecialchars(), a ->text() chain,
     * an Htmlable or its ->toHtml(), a children() mapper, a non-builder receiver,
     * and an href (which spatie escapes) without content.
     */
    #[Test]
    public function flags_unescaped_content_arguments(): void
    {
        $this->analyse([__DIR__ . '/stubs/builder-content.php'], [
            [$this->message('a'), 18, $this->tip()],         // plain string variable
            [$this->message('span'), 19, $this->tip()],      // interpolation
            [$this->message('div'), 20, $this->tip()],       // concatenation
            [$this->message('p'), 21, $this->tip()],         // sprintf
            [$this->message('html'), 22, $this->tip()],      // ->html() on an element
            [$this->message('children'), 23, $this->tip()],  // array of strings
            [$this->message('a'), 24, $this->tip()],         // named content argument
        ]);
    }

    #[Test]
    public function accepts_an_ignore_comment_with_a_reason(): void
    {
        $this->analyse([__DIR__ . '/stubs/builder-ignored.php'], []);
    }

    #[Test]
    public function ignores_code_outside_the_configured_namespaces(): void
    {
        $this->analyse([__DIR__ . '/stubs/builder-outside-namespace.php'], []);
    }

    #[Test]
    public function reports_nothing_when_no_namespaces_are_configured(): void
    {
        $this->namespaces = [];

        $this->analyse([__DIR__ . '/stubs/builder-content.php'], []);
    }

    private function message(string $method): string
    {
        return "{$method}() renders its content without escaping it, and this value can contain HTML.";
    }

    private function tip(): string
    {
        return 'Pass the value through ->text() or e() first. If it is trusted HTML, add @phpstan-ignore hihaho.html.unescapedBuilderContent (reason).';
    }
}
