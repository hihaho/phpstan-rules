<?php declare(strict_types=1);

namespace Tests;

class ChainedDebugInTestNamespaceStub
{
    public function test(): void
    {
        collect(['a', 'b'])->dump();
        collect(['a', 'b'])->dd();
    }
}
