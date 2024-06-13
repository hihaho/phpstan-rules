<?php declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\Http;

class StaticChainedDebugInTestNamespaceStub
{
    public function test(): void
    {
        Http::dump()->get('https://example.com');
        Http::dd()->get('https://example.com');
    }
}
