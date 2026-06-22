<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing;

use Illuminate\Http\Request;

use function PHPStan\Testing\assertType;

/**
 * Regression guard for the larastan environment. larastan ships its own broad `Request::route()`
 * return-type extension (`object|string|null`) and changes how facade calls surface from the default
 * analysis parser. This asserts the bound model still wins under larastan — the env every real
 * consumer runs, and the one the package's other tests don't exercise.
 */
final class RouteBindingLarastanTarget
{
    public function exercise(Request $request): void
    {
        assertType(Video::class, $request->route('video'));
        assertType(Locale::class, $request->route('locale'));
        assertType(Subtitle::class, $request->route('subtitle'));

        // Inherited boot() binding, resolved under larastan too.
        assertType(Locale::class, $request->route('inherited'));
    }
}
