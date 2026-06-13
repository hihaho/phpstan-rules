<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class OpaqueVariableReturnFormRequestStub extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string',
        ];

        $rules['email'] = 'required|email';

        return $rules;
    }

    public function describe(): string
    {
        return (string) $this->string('anything');
    }
}
