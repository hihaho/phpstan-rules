<?php declare(strict_types=1);

namespace App;

use Illuminate\Support\Facades\Route;

class DynamicStaticCallInAppNamespace
{
    public function test(): void
    {
        $class = Route::class;
        $class::current();
    }
}
