<?php declare(strict_types=1);

namespace App;

use Illuminate\Support\Facades\Http;

class AllStaticChainedDebugInAppNamespaceStub
{
    public function testDump(): void
    {
        Http::dump()->get('https://example.com');
    }

    public function testDd(): void
    {
        Http::dd()->get('https://example.com');
    }
}
