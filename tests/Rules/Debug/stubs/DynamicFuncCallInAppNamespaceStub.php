<?php declare(strict_types=1);

namespace App;

final class DynamicFuncCallInAppNamespaceStub
{
    public function test(): void
    {
        $fn = 'dump';
        $fn('value');
    }
}
