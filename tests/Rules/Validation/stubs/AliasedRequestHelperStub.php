<?php declare(strict_types=1);

namespace App\Http\Controllers;

use function request as req;

final class AliasedRequestHelperStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'a' => req('a'),
            'b' => req('b'),
        ];
    }
}
