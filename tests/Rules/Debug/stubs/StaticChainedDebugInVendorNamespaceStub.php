<?php declare(strict_types=1);

namespace Vendor;

use Illuminate\Support\Facades\Http;

class StaticChainedDebugInVendorNamespaceStub
{
    public function test(): void
    {
        Http::dump()->get('https://example.com');
        Http::dd()->get('https://example.com');
    }
}
