<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class FormRequestInternalStub extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
        ];
    }

    public function normalizedName(): ?string
    {
        $value = $this->input('name');

        return is_string($value) ? trim($value) : null;
    }
}
