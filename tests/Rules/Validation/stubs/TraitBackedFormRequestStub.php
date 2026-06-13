<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReadsUnvalidatedFieldTrait;
use Illuminate\Foundation\Http\FormRequest;

final class TraitBackedFormRequestStub extends FormRequest
{
    use ReadsUnvalidatedFieldTrait;

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
        ];
    }
}
