<?php declare(strict_types=1);

namespace Vendor;

class ChainedDebugInVendorNamespaceStub
{
    public function test(): void
    {
        collect(['a', 'b'])->dump();
        collect(['a', 'b'])->dd();
    }
}
