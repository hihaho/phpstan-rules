<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class NestedRuleKeyFormRequestStub extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'address.street' => 'required|string',
            'items.*.id' => 'required|integer',
        ];
    }

    public function describe(): string
    {
        $address = $this->input('address');
        $items = $this->input('items');

        return json_encode([$address, $items]) ?: '';
    }
}
