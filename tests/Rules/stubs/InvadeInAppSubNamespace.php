<?php declare(strict_types=1);

namespace App\Services;

use stdClass;

class InvadeInAppSubNamespace
{
    public function test(): void
    {
        $obj = new stdClass();
        $invaded = invade($obj);
    }
}
