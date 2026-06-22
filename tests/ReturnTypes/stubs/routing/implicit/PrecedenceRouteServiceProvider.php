<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PrecedenceRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Explicitly bind {video} to VideoContainer — the route file implicitly binds it to Video.
        Route::model('video', VideoContainer::class);
    }
}
