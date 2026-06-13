<?php declare(strict_types=1);

namespace App\Http\Requests;

final class ChildOfOpaqueBaseFormRequest extends InheritedOpaqueBaseFormRequest
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
        return (string) $this->string('injected');
    }
}
