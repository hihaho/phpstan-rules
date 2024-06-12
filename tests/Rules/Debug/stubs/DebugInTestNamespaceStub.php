<?php declare(strict_types=1);

namespace Tests;

use stdClass;

class DebugInTestNamespaceStub
{
    public function test(): void
    {
        $obj = new stdClass;
        ray('fooda');
        dump($obj);
        dd('fooda');
    }
}
