<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Request;

final class RequestFacadeSafeMethodStub
{
    public function __invoke(): bool
    {
        return Request::ajax();
    }
}
