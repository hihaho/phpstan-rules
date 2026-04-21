<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

final class HttpRequestStaticCallStub
{
    public function __invoke(): Request
    {
        return Request::capture();
    }
}
