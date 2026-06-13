<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AllValidatedFormRequestStub extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'age' => 'required|integer',
        ];
    }

    public function describe(): string
    {
        $name = $this->string('name');
        $age = $this->integer('age');

        return "{$name} ({$age})";
    }
}
