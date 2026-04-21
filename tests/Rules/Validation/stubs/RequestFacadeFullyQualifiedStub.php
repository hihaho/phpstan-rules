<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Request;

final class RequestFacadeFullyQualifiedStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'a' => Request::boolean('a'),
        ];
    }
}
