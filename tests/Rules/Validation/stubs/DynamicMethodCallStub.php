<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

final class DynamicMethodCallStub
{
    public function __invoke(Request $request, string $method): mixed
    {
        return $request->{$method}('foo');
    }
}
