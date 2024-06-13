<?php declare(strict_types=1);

namespace App;

class ChainedDebugInAppNamespaceStub
{
    public function test(): void
    {
        collect(['a', 'b'])->dump();
        collect(['a', 'b'])->dd();
    }
}
