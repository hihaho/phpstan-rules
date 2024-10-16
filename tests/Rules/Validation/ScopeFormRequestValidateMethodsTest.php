<?php declare(strict_types=1);

namespace Rules\Validation;

use Hihaho\PhpstanRules\Rules\Validation\ScopeFormRequestValidateMethods;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ScopeFormRequestValidateMethodsTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ScopeFormRequestValidateMethods();
    }

    #[Test]
    public function form_request_class_does_not_use_unvalidated_data_outside_its_namespace(): void
    {
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
