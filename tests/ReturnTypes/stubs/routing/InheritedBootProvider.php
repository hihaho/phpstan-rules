<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

abstract class BaseRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::model('inherited', Locale::class);
    }
}

// Inherits boot() from the base — its own file declares no bindings. The extension must parse the
// base file (where boot() is declared) to find them.
final class InheritingRouteServiceProvider extends BaseRouteServiceProvider {}
