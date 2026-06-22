<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class Video {}

final class Locale {}

final class Playlist {}

final class Subtitle {}

final class RouteParams
{
    public const string SUBTITLE = 'subtitle';
}

final class AppRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Route::model binding — second arg is a ::class constant.
        Route::model('video', Video::class);

        // Route::model with a missing-model callback — skipped (the callback can replace the model).
        Route::model('guarded', Video::class, fn () => abort(404));

        // Route::bind closure with an explicit return-type hint.
        Route::bind('locale', fn (string $value): Locale => new Locale());

        // Route::bind closure with a nullable return-type hint — narrowed to the non-null model.
        Route::bind('playlist', fn (string $value): ?Playlist => $value === '' ? null : new Playlist());

        // Route::bind closure with a union return-type hint — narrowed to the sole class member.
        Route::bind('union_playlist', fn (string $value): Playlist|null => $value === '' ? null : new Playlist());

        // Route::model with a class-constant parameter name (RouteParams::SUBTITLE === 'subtitle').
        Route::model(RouteParams::SUBTITLE, Subtitle::class);

        // Route::bind closure with a built-in (non-class) return type — skipped (not a bound model).
        Route::bind('idp_name', fn (string $value): string => $value);

        // Route::bind closure without a return-type hint — skipped (binding type unprovable).
        Route::bind('dynamic', fn (string $value) => $value);
    }
}
