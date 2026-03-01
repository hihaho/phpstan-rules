<?php declare(strict_types=1);

namespace App;

class AllDebugInAppNamespaceStub
{
    public function testDump(): void
    {
        dump('test');
    }

    public function testDd(): void
    {
        dd('test');
    }

    public function testDdd(): void
    {
        ddd('test');
    }

    public function testRay(): void
    {
        ray('test');
    }

    public function testPrintR(): void
    {
        print_r('test');
    }

    public function testVarDump(): void
    {
        var_dump('test');
    }
}
