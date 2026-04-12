<?php declare(strict_types=1);

namespace App;

use Illuminate\Support\Facades\Cache;

class UnannotatedFacadeStaticDumpStub
{
    public function test(): void
    {
        // `Cache` doesn't declare `dump`/`dd` via `@method` annotations; the
        // call is proxied through `Facade::__callStatic` at runtime. Must
        // still be flagged as a debug call — the Facade-subclass fallback
        // catches this.
        Cache::dump();
        Cache::dd();
    }
}
