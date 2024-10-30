<?php declare(strict_types=1);

namespace Rules\Validation;

use Hihaho\PhpstanRules\Rules\Validation\ScopeIlluminateRequestValidateMethods;
use PHPStan\Analyser\Error;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<ScopeIlluminateRequestValidateMethods>
 */
final class ScopeIlluminateRequestValidateMethodTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ScopeIlluminateRequestValidateMethods([
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

        $unsafeRequestDataError = static fn (int $line) => [
            'Usage of unvalidated request data is not allowed outside of App\\Http\\Requests',
            $line,
            'Use $request->safe() to use request data',
        ];

       $this->analyse([__DIR__ . '/../../stubs/App/Http/Controllers/PeopleControllerStub.php'], [
            $unsafeRequestDataError(13),
            $unsafeRequestDataError(14),
            $unsafeRequestDataError(15),
            $unsafeRequestDataError(16),
            $unsafeRequestDataError(18),
            $unsafeRequestDataError(21),
            [
                'No error with identifier method.notFound is reported on line 20.',
                20,
            ],
        ]);

    }
}