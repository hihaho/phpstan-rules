<?php declare(strict_types=1);

namespace App;

use Route;
use Custom;

class InvadeInAppNamespace
{
    public function test()
    {
        $current = Route::current();

        $custom = Custom::trala();
    }
}
