<?php declare(strict_types=1);

namespace Test;

use stdClass;

class DebugInTestNamespace
{
    public function test(): void
    {
        $obj = new stdClass;
        ray('fooda');
        dump($obj);
        dd('fooda');
    }
}
