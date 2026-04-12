<?php declare(strict_types=1);

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

final class UserCustomFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'custom-service';
    }
}

namespace App;

use App\Facades\UserCustomFacade;

final class UsesUserCustomFacade
{
    public function test(): void
    {
        // `UserCustomFacade` has no `@method static ... dump()` annotation,
        // but it extends `Illuminate\Support\Facades\Facade` so the call
        // proxies to the underlying service via `__callStatic`. Must flag
        // via the Facade-subclass fallback in isLaravelStaticDebugCall().
        UserCustomFacade::dump();
        UserCustomFacade::dd();
    }
}
