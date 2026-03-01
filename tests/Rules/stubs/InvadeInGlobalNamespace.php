<?php declare(strict_types=1);

use stdClass;

class InvadeInGlobalNamespace
{
    public function test(): void
    {
        $obj = new stdClass();
        $invaded = invade($obj);
    }
}
