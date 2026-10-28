<?php declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class ClosureRuleFormRequestStub extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === 'reserved') {
                        $fail('The code is reserved.');

                        return;
                    }
                },
                fn (): string => 'string',
            ],
        ];
    }

    public function referral(): ?string
    {
        return $this->string('referral')->toString();
    }
}
