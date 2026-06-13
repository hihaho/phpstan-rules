<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class OpaqueMergeFormRequestStub extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return array_merge(['name' => 'required'], $this->extraRules());
    }

    /**
     * @return array<string, string>
     */
    private function extraRules(): array
    {
        return ['age' => 'integer'];
    }

    public function describe(): string
    {
        return (string) $this->string('anything');
    }
}
