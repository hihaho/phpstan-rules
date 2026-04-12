<?php declare(strict_types=1);

namespace App;

use Illuminate\Support\Facades\Http;

final class DynamicStaticCallInAppNamespaceStub
{
    public function test(): void
    {
        $method = 'dump';
        Http::{$method}();
    }
}
