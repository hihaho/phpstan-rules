<?php declare(strict_types=1);

namespace Rules;

use Hihaho\PhpstanRules\Rules\ScopeRequestValidateMethods;
use PHPStan\Analyser\Error;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ScopeRequestValidateMethods>
 */
final class ScopeRequestValidateMethodTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ScopeRequestValidateMethods();
    }

    public function testRule(): void
    {
        /** @var Error[] $errors */
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/../stubs/App/Http/Controllers/PeopleControllerStub.php']);
        $validErrors = array_filter(
            $errors,
            static fn (Error $error): bool => $error->getIdentifier() === 'hihaho.request.unsafeRequestData'
        );
        self::assertCount(6, $validErrors);

       $this->analyse([__DIR__ . '/../stubs/App/Http/Controllers/PeopleControllerStub.php'], [
            [
                'Usage of unvalidated request data is not allowed outside of App\\Http\\Requests',
                13,
                'Use $request->safe() to use request data',
            ],
            [
                'Usage of unvalidated request data is not allowed outside of App\\Http\\Requests',
                14,
                'Use $request->safe() to use request data',
            ],
            [
                'Usage of unvalidated request data is not allowed outside of App\\Http\\Requests',
                15,
                'Use $request->safe() to use request data',
            ],
            [
                'Usage of unvalidated request data is not allowed outside of App\\Http\\Requests',
                16,
                'Use $request->safe() to use request data',
            ],
            [
                'Usage of unvalidated request data is not allowed outside of App\\Http\\Requests',
                18,
                'Use $request->safe() to use request data',
            ],
            [
                'Usage of unvalidated request data is not allowed outside of App\\Http\\Requests',
                21,
                'Use $request->safe() to use request data',
            ],
            [
                'No error with identifier method.notFound is reported on line 20.',
                20,
            ],
        ]);

        /** @var Error[] $errors */
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/../stubs/App/Http/Requests/UserRequest.php']);
        self::assertCount(0, $errors);
        $this->analyse([__DIR__ . '/../stubs/App/Http/Requests/UserRequest.php'], []);
    }
}
