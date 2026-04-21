<?php declare(strict_types=1);

namespace App\Http\Controllers;

final class RequestHelperWithArgStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'a' => request('a'),
            'b' => request('b', 'default'),
        ];
    }
}
