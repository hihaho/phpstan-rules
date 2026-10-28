<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Html;

use Hihaho\PhpstanRules\Rules\Html\HtmlFieldReturnSink;
use Hihaho\PhpstanRules\Rules\Html\HtmlSafetyJudge;
use Hihaho\PhpstanRules\Rules\Html\UnescapedHtmlFieldClosureRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<UnescapedHtmlFieldClosureRule>
 */
final class UnescapedHtmlFieldClosureRuleTest extends RuleTestCase
{
    public const string MESSAGE = "This asHtml() field renders the closure's return value without escaping it, and the value can contain HTML.";

    public const string TIP = 'Escape user values with e(), or build the markup with spatie/laravel-html and ->text(). If it is trusted HTML, add @phpstan-ignore hihaho.html.unescapedHtmlField (reason).';

    /** @var list<string> */
    private array $namespaces = ['App'];

    #[Override]
    protected function getRule(): Rule
    {
        return new UnescapedHtmlFieldClosureRule(
            new HtmlFieldReturnSink($this->namespaces, [], new HtmlSafetyJudge(), self::createReflectionProvider()),
        );
    }

    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/config.neon'];
    }

    /**
     * Each return is judged with its own scope: the literal early return in
     * the resolveUsing() closure is fine, the $value return after it is not.
     * Below the flagged fields: e(), a ->text() chain rendered with toHtml(),
     * and a field without asHtml().
     */
    #[Test]
    public function flags_unescaped_closure_returns(): void
    {
        $this->analyse([__DIR__ . '/stubs/nova-field-closures.php'], [
            [self::MESSAGE, 14, self::TIP],  // displayUsing() concatenation
            [self::MESSAGE, 21, self::TIP],  // resolveUsing() mixed return
            [self::MESSAGE, 24, self::TIP],  // computed make() callback
        ]);
    }

    #[Test]
    public function reports_nothing_when_no_namespaces_are_configured(): void
    {
        $this->namespaces = [];

        $this->analyse([__DIR__ . '/stubs/nova-field-closures.php'], []);
    }
}
