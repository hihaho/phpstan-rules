<?php declare(strict_types=1);

namespace Rules\Validation;

use Hihaho\PhpstanRules\Rules\Validation\ScopeFormRequestMethods;
use PHPStan\Analyser\Error;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ScopeFormRequestMethodsTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ScopeFormRequestMethods();
    }

    #[Test]
    public function form_request_class_does_not_use_unvalidated_data_outside_its_namespace(): void
    {
        /** @var Error[] $errors */
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/../../stubs/App/Http/Requests/UserRequest.php']);
        self::assertCount(0, $errors);
        $this->analyse([__DIR__ . '/../../stubs/App/Http/Requests/UserRequest.php'], []);

        $this->analyse([__DIR__ . '/../../stubs/App/Http/Controllers/PetControllerStub.php'], [
            [
                'Usage of unvalidated request data is not allowed outside of App\\Http\\Requests',
                14,
                'Use $request->safe() to use request data',
            ],
        ]);
    }
}
