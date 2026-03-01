<?php declare(strict_types=1);

namespace Application;

use stdClass;

class InvadeInApplicationNamespace
{
    public function test(): void
    {
        $obj = new stdClass();
        $invaded = invade($obj);
    }
}
