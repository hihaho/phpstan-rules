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

    public function testDdd(): void
    {
        Http::ddd()->get('https://example.com');
    }

    public function testRay(): void
    {
        Http::ray()->get('https://example.com');
    }

    public function testPrintR(): void
    {
        Http::print_r()->get('https://example.com');
    }

    public function testVarDump(): void
    {
        Http::var_dump()->get('https://example.com');
    }
}
