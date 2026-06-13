<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SpreadRulesFormRequestStub extends FormRequest
{
    /** @var array<string, string> */
    private array $base = ['name' => 'required'];

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            ...$this->base,
            'age' => 'integer',
        ];
    }

    public function describe(): string
    {
        return (string) $this->string('anything');
    }
}
