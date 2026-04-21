<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\Rules\Validation;

use Hihaho\PhpstanRules\Rules\Validation\NoUnsafeRequestFacadeRule;
use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<NoUnsafeRequestFacadeRule>
 */
final class NoUnsafeRequestFacadeRuleTest extends RuleTestCase
{
    private const string MESSAGE_PATTERN = 'Reading unvalidated request data via Illuminate\Support\Facades\Request::%s() is not allowed. Use a FormRequest, $request->validated(), or $request->safe().';

    private const string TIP = 'Inject a FormRequest subclass, or call $request->validated() / $request->safe() before reading input.';

    #[Override]
    protected function getRule(): Rule
    {
        return new NoUnsafeRequestFacadeRule(
            unsafeMethods: [
                'input', 'all', 'get', 'query', 'post', 'only', 'except', 'collect',
                'string', 'str', 'integer', 'boolean', 'float', 'json', 'keys',
                'fluent', 'array', 'date', 'enum', 'enums',
            ],
            namespaces: ['App'],
        );
    }

    #[Test]
    public function flags_unsafe_static_calls_on_facade_via_import(): void
    {
        $this->analyse([__DIR__ . '/stubs/RequestFacadeCallStub.php'], [
            [sprintf(self::MESSAGE_PATTERN, 'boolean'), 15, self::TIP],
            [sprintf(self::MESSAGE_PATTERN, 'input'), 16, self::TIP],
            [sprintf(self::MESSAGE_PATTERN, 'all'), 17, self::TIP],
        ]);
    }

    #[Test]
    public function flags_fully_qualified_facade_calls(): void
    {
        $this->analyse([__DIR__ . '/stubs/RequestFacadeFullyQualifiedStub.php'], [
            [sprintf(self::MESSAGE_PATTERN, 'boolean'), 15, self::TIP],
        ]);
    }

    #[Test]
    public function does_not_flag_non_unsafe_facade_methods(): void
    {
        $this->analyse([__DIR__ . '/stubs/RequestFacadeSafeMethodStub.php'], []);
    }

    #[Test]
    public function does_not_flag_facade_calls_outside_configured_namespace(): void
    {
        $this->analyse([__DIR__ . '/stubs/RequestFacadeOutsideNamespaceStub.php'], []);
    }

    #[Test]
    public function does_not_flag_illuminate_http_request_static_calls(): void
    {
        $this->analyse([__DIR__ . '/stubs/HttpRequestStaticCallStub.php'], []);
    }

    #[Test]
    public function error_uses_correct_identifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/stubs/RequestFacadeCallStub.php']);

        $this->assertNotEmpty($errors);

        foreach ($errors as $error) {
            $this->assertSame('hihaho.validation.noUnsafeRequestFacade', $error->getIdentifier());
        }
    }
}
