<?php declare(strict_types=1);

namespace App\Http\Controllers;

final class RequestHelperChainStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'a' => request()->input('a'),
            'b' => request()->all(),
        ];
    }
}
