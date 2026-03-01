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

    public function testDdd(): void
    {
        collect()->ddd();
    }

    public function testRay(): void
    {
        collect()->ray();
    }

    public function testPrintR(): void
    {
        collect()->print_r();
    }

    public function testVarDump(): void
    {
        collect()->var_dump();
    }
}
