<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

final class MixedCaseMethodStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request): array
    {
        return [
            'a' => $request->INPUT('a'),
            'b' => $request->All(),
            'c' => $request->GeT('c'),
        ];
    }
}
