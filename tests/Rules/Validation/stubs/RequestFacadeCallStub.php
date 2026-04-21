<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Request;

final class RequestFacadeCallStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'direct' => Request::boolean('direct'),
            'debug' => Request::input('debug'),
            'keys' => Request::all(),
        ];
    }
}
