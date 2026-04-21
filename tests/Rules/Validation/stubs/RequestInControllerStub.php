<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

final class RequestInControllerStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request): array
    {
        return [
            'a' => $request->input('a'),
            'b' => $request->all(),
            'c' => $request->get('c'),
            'd' => $request->only(['d']),
        ];
    }
}
