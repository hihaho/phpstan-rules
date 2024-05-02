<?php declare(strict_types=1);

namespace App;

use Custom;
use Route;

class InvadeInAppNamespace
{
    public function test()
    {
        $current = Route::current();

        $custom = Custom::test();
    }
}
