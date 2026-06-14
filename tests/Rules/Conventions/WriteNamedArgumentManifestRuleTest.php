<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Conventions;

use Hihaho\PhpstanRules\Collectors\FlagArgumentManifestCollector;
use Hihaho\PhpstanRules\Rules\Conventions\WriteNamedArgumentManifestRule;
use Override;
use PhpParser\Node;
use PHPStan\Collectors\Collector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<WriteNamedArgumentManifestRule>
 */
final class WriteNamedArgumentManifestRuleTest extends RuleTestCase
{
    private string $manifestPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifestPath = (string) tempnam(sys_get_temp_dir(), 'phpstan-rules-manifest-');
    }

    protected function tearDown(): void
    {
        if ($this->manifestPath !== '' && file_exists($this->manifestPath)) {
            unlink($this->manifestPath);
        }

        parent::tearDown();
    }

    #[Override]
    protected function getRule(): Rule
    {
        return new WriteNamedArgumentManifestRule($this->manifestPath);
    }

    /**
     * @return list<Collector<Node, mixed>>
     */
    #[Override]
    protected function getCollectors(): array
    {
        return [
            new FlagArgumentManifestCollector(
                $this->createReflectionProvider(),
                ['App', 'Database\\Factories', 'Tests'],
            ),
        ];
    }

    #[Test]
    public function it_writes_records_for_method_call_flag_sites(): void
    {
        $this->analyse([__DIR__ . '/stubs/FlagMethodCallStub.php'], []);

        $json = (string) file_get_contents($this->manifestPath);

        $this->assertStringContainsString('FlagMethodCallStub.php', $json);
        $this->assertStringContainsString('"method": "setActive"', $json);
        $this->assertStringContainsString('"paramName": "active"', $json);
        $this->assertStringContainsString('"value": "true"', $json);
        $this->assertStringContainsString('"method": "configure"', $json);
        $this->assertStringContainsString('"paramName": "option"', $json);
        $this->assertStringContainsString('"value": "null"', $json);

        // named, variadic, and non-last-flag sites must not be recorded.
        $this->assertStringNotContainsString('"paramName": "text"', $json);
        $this->assertStringNotContainsString('"paramName": "flags"', $json);
    }

    #[Test]
    public function it_writes_records_for_static_and_constructor_flag_sites(): void
    {
        $this->analyse([
            __DIR__ . '/stubs/StaticFlagCallStub.php',
            __DIR__ . '/stubs/ConstructorFlagCallStub.php',
        ], []);

        $json = (string) file_get_contents($this->manifestPath);

        // static call: StaticFlag::toggle('name', false)
        $this->assertStringContainsString('"method": "toggle"', $json);
        $this->assertStringContainsString('"paramName": "on"', $json);

        // constructor: new Widget('name', true) — method is the resolved FQCN
        $this->assertStringContainsString('App\\\\Models\\\\Widget', $json);
        $this->assertStringContainsString('"paramName": "visible"', $json);
    }

    #[Test]
    public function it_writes_records_for_nullsafe_method_call_flag_sites(): void
    {
        $this->analyse([__DIR__ . '/stubs/NullsafeFlagCallStub.php'], []);

        $json = (string) file_get_contents($this->manifestPath);

        $this->assertStringContainsString('"method": "setActive"', $json);
        $this->assertStringContainsString('"paramName": "active"', $json);
        $this->assertStringContainsString('"value": "true"', $json);

        // PHPStan visits a nullsafe call in two scopes; the writer must dedup to
        // a single record (not one per scope visit).
        $this->assertSame(1, substr_count($json, '"method": "setActive"'));
    }

    #[Test]
    public function it_keeps_two_distinct_same_line_calls_as_separate_records(): void
    {
        $this->analyse([__DIR__ . '/stubs/SameLineNullsafeFlagsStub.php'], []);

        $json = (string) file_get_contents($this->manifestPath);

        // Two distinct nullsafe calls share line/method/argIndex/paramName/value
        // but are separate sites — dedup must key on position, not content.
        $this->assertSame(2, substr_count($json, '"method": "setActive"'));
    }
}
