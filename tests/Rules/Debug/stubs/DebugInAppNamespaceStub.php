<?php declare(strict_types=1);

namespace App;

use stdClass;

class DebugInAppNamespaceStub
{
    public function test(): void
    {
        $obj = new stdClass;
        ray('fooda');
        dump($obj);
        dd('fooda');
    }
}
