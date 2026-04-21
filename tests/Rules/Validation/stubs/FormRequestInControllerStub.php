<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SharedUserFormRequest;

final class FormRequestInControllerStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(SharedUserFormRequest $request): array
    {
        return [
            'a' => $request->all(),
            'b' => $request->input('b'),
        ];
    }
}
