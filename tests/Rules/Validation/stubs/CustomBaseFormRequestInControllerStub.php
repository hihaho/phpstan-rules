<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CustomBaseFormRequestStub;

final class CustomBaseFormRequestInControllerStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(CustomBaseFormRequestStub $request): array
    {
        return [
            'a' => $request->all(),
            'b' => $request->input('b'),
        ];
    }
}
