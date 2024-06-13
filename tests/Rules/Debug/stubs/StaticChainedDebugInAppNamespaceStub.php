<?php declare(strict_types=1);

namespace App;

use Illuminate\Support\Facades\Http;

class StaticChainedDebugInAppNamespaceStub
{
    public function test(): void
    {
        Http::dump()->get('https://example.com');
        Http::dd()->get('https://example.com');
    }
}
