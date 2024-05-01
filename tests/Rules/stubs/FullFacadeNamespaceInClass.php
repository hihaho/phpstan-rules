<?php declare(strict_types=1);

namespace App;

use App\Facades\Custom;
use Illuminate\Support\Facades\Route;

class InvadeInAppNamespace
{
    public function test()
    {
        $current = Route::current();

        $test = Custom::test();
    }
}
