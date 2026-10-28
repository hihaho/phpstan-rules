<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Conventions;

use App\InheritableSettings\VideoStub;
use Hihaho\PhpstanRules\Rules\Conventions\InheritableSettingReadRule;
use InvalidArgumentException;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<InheritableSettingReadRule>
 */
final class InheritableSettingReadRuleTest extends RuleTestCase
{
    private const string TIP = 'Use the resolved getter, which falls back to the inherited value. If the raw column is what you need, add @phpstan-ignore hihaho.conventions.inheritableSettingRead (reason).';

    /** @var array<string, list<string>> */
    private array $inheritableSettings = [
        VideoStub::class => ['autoplay', 'theme'],
    ];

    #[Override]
    protected function getRule(): Rule
    {
        return new InheritableSettingReadRule($this->inheritableSettings, self::createReflectionProvider());
    }

    /**
     * Below the flagged reads: a column that does not inherit, the same column
     * name on another class, and writes, which is how a setting goes back to
     * inheriting.
     */
    #[Test]
    public function flags_direct_reads_of_inheritable_settings(): void
    {
        $this->analyse([__DIR__ . '/stubs/InheritableSettings/reads.php'], [
            [$this->message('autoplay'), 9, self::TIP],   // plain read
            [$this->message('theme'), 10, self::TIP],     // nullsafe read on a nullable receiver
            [$this->message('autoplay'), 11, self::TIP],  // read through a subclass
            [$this->message('theme'), 12, self::TIP],     // isset() still reads the raw column
        ]);
    }

    #[Test]
    public function accepts_an_ignore_comment_with_a_reason(): void
    {
        $this->analyse([__DIR__ . '/stubs/InheritableSettings/models.php'], []);
    }

    #[Test]
    public function reports_nothing_when_no_settings_are_configured(): void
    {
        $this->inheritableSettings = [];

        $this->analyse([__DIR__ . '/stubs/InheritableSettings/reads.php'], []);
    }

    #[Test]
    public function rejects_an_unknown_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Parameter inheritableSettings: 'App\\Missing' is not an existing class.");

        new InheritableSettingReadRule(['App\\Missing' => ['autoplay']], self::createReflectionProvider());
    }

    private function message(string $property): string
    {
        return "Reading VideoStub::\${$property} directly ignores inheritance: null means inherit.";
    }
}
