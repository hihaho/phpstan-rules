<?php declare(strict_types=1);

namespace App;

use Illuminate\Support\Collection;

final class DynamicMethodCallInAppNamespaceStub
{
    public function test(Collection $collection): void
    {
        $method = 'dump';
        $collection->{$method}();
    }
}
