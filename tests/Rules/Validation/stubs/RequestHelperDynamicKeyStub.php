<?php declare(strict_types=1);

namespace App\Http\Controllers;

final class RequestHelperDynamicKeyStub
{
    public function __invoke(string $key): mixed
    {
        return request($key);
    }
}
