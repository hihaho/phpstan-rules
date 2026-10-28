<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Exceptions;

use Hihaho\PhpstanRules\Rules\Exceptions\CatchBodyInspector;
use Hihaho\PhpstanRules\Rules\Exceptions\SwallowedExceptionRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<SwallowedExceptionRule>
 */
final class SwallowedExceptionRuleTest extends RuleTestCase
{
    private const string TIP = 'Rethrow it, pass it to report(), or read the caught variable. If swallowing is deliberate, add @phpstan-ignore hihaho.exceptions.swallowedException (reason).';

    private const string FIXTURE = __DIR__ . '/stubs/swallowed-exceptions.php';

    /** @var list<string> */
    private array $namespaces = ['App'];

    /** @var list<string> */
    private array $excludeNamespaces = [];

    private bool $reportNarrowCatches = false;

    #[Override]
    protected function getRule(): Rule
    {
        return new SwallowedExceptionRule(
            $this->namespaces,
            $this->excludeNamespaces,
            $this->reportNarrowCatches,
            self::getContainer()->getByType(CatchBodyInspector::class),
        );
    }

    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/config.neon'];
    }

    /**
     * Each line is one flag case in the fixture; every other catch in it is an
     * exemption that must stay silent: report/throw/Log/logger/abort, fail() and
     * release(), a read of the caught variable (also inside a closure), a bool
     * probe, a nullable try*, a typed denial, a validation $fail, and a catch
     * nested in a reporting catch.
     */
    #[Test]
    public function flags_broad_catches_that_swallow_the_exception(): void
    {
        $this->analyse([self::FIXTURE], [
            [$this->message('Throwable'), 18, self::TIP],   // empty body
            [$this->message('Exception'), 26, self::TIP],   // fallback value, $e never read
            [$this->message('Throwable'), 35, self::TIP],   // $e only overwritten
            [$this->message('Throwable'), 44, self::TIP],   // ->failed() records success
            [$this->message('Throwable'), 53, self::TIP],   // Throwable in a multi-type catch
            [$this->message('Throwable'), 163, self::TIP],  // isolate*() is not an is*() probe
            [$this->message('Throwable'), 181, self::TIP],  // try*() that cannot return null
            [$this->message('Throwable'), 226, self::TIP],  // only the nested catch reports
        ]);
    }

    #[Test]
    public function flags_narrow_catches_when_enabled(): void
    {
        $this->reportNarrowCatches = true;

        $this->analyse([self::FIXTURE], [
            [$this->message('Throwable'), 18, self::TIP],
            [$this->message('Exception'), 26, self::TIP],
            [$this->message('Throwable'), 35, self::TIP],
            [$this->message('Throwable'), 44, self::TIP],
            [$this->message('RuntimeException'), 53, self::TIP],
            [$this->message('InvalidArgumentException'), 61, self::TIP],
            [$this->message('Throwable'), 163, self::TIP],
            [$this->message('Throwable'), 181, self::TIP],
            [$this->message('Throwable'), 226, self::TIP],
        ]);
    }

    #[Test]
    public function accepts_an_ignore_comment_with_a_reason(): void
    {
        $this->analyse([__DIR__ . '/stubs/swallowed-ignored.php'], []);
    }

    #[Test]
    public function ignores_code_outside_the_configured_namespaces(): void
    {
        $this->analyse([__DIR__ . '/stubs/swallowed-outside-namespace.php'], []);
    }

    #[Test]
    public function ignores_excluded_namespaces(): void
    {
        $this->excludeNamespaces = ['App\Exceptions'];

        $this->analyse([self::FIXTURE], []);
    }

    #[Test]
    public function reports_nothing_when_no_namespaces_are_configured(): void
    {
        $this->namespaces = [];

        $this->analyse([self::FIXTURE], []);
    }

    private function message(string $type): string
    {
        return "Catching {$type} without rethrowing, reporting or reading it turns every failure into silent success.";
    }
}
