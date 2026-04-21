<?php declare(strict_types=1);

namespace Vendor\Package;

use Illuminate\Support\Facades\Request;

final class RequestFacadeOutsideNamespaceStub
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'a' => Request::input('a'),
        ];
    }
}
