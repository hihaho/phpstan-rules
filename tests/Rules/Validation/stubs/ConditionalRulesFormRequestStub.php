<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConditionalRulesFormRequestStub extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'create_only' => 'required|string',
            ];
        }

        return [
            'update_only' => 'required|string',
        ];
    }

    public function describe(): string
    {
        return (string) $this->string('create_only');
    }
}
