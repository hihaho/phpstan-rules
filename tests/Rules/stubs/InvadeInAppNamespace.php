<?php declare(strict_types=1);

namespace App;

use stdClass;

class InvadeInAppNamespace
{
    public function test()
    {
        $obj = new stdClass;
        $invaded = invade($obj);
    }
}
