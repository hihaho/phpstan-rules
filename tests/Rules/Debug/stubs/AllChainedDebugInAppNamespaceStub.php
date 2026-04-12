<?php declare(strict_types=1);

namespace App;

class AllChainedDebugInAppNamespaceStub
{
    public function testDump(): void
    {
        collect()->dump();
    }

    public function testDd(): void
    {
        collect()->dd();
    }
}
