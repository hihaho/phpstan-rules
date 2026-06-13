<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UnvalidatedFormRequestFieldStub extends FormRequest
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

    public function shouldRedirect(): bool
    {
        return $this->boolean('submit_redirect');
    }

    public function normalizedName(): ?string
    {
        $value = $this->input('name');

        return is_string($value) ? trim($value) : null;
    }
}
