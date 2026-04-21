<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;

final class DocblockTypedReceiverStub
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(mixed $request): array
    {
        return [
            'name' => $request->input('name'),
        ];
    }
}
