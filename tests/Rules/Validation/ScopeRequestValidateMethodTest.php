<?php declare(strict_types=1);

namespace Rules\Validation;

use Hihaho\PhpstanRules\Rules\Validation\ScopeRequestValidateMethods;
use PHPStan\Analyser\Error;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<ScopeRequestValidateMethods>
 */
final class ScopeRequestValidateMethodTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ScopeRequestValidateMethods([
            'collect',
            'all',
            'only',
            'except',
            'input',
            'get',
            'keys',
            'string',
            'str',
            'integer',
            'float',
            'boolean',
        ]);
    }

    #[Test]
    public function illuminate_http_request_does_not_use_unvalidated_methods_outside_app_http_requests_namespace(): void
    {
        /** @var Error[] $errors */
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/../../stubs/App/Http/Controllers/PeopleControllerStub.php']);
        $validErrors = array_filter(
            $errors,
            static fn (Error $error): bool => $error->getIdentifier() === 'hihaho.request.unsafeRequestData'
        );

        self::assertCount(6, $validErrors);

        foreach ($validErrors as $key => $error) {
            self::assertStringContainsString('Usage of unvalidated request data is not allowed outside of App\\Http\\Requests', $error->getMessage());
            self::assertFalse($error->canBeIgnored());
            self::assertStringContainsString('Use $request->safe() / $request->validated() to use request data', $error->getTip());
            self::assertStringContainsString('Current checking: variable request, method', $error->getTip());
            match($key) {
                0 => self::assertSame(13, $error->getNodeLine()),
                1 => self::assertSame(14, $error->getNodeLine()),
                2 => self::assertSame(15, $error->getNodeLine()),
                3 => self::assertSame(16, $error->getNodeLine()),
                4 => self::assertSame(18, $error->getNodeLine()),
                5 => self::assertSame(21, $error->getNodeLine()),
            };
        }
    }
}
