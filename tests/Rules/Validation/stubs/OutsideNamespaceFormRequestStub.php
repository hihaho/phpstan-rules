<?php declare(strict_types=1);

namespace Vendor\Package\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class OutsideNamespaceFormRequestStub extends FormRequest
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

    public function describe(): string
    {
        return (string) $this->string('unvalidated');
    }
}
