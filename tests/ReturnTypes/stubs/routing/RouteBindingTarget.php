<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

use function PHPStan\Testing\assertType;

final class RouteBindingTarget
{
    public function viaRequest(Request $request): void
    {
        // Route::model binding resolves to the bound model.
        assertType(Video::class, $request->route('video'));

        // Route::bind closure with a return-type hint resolves to that type.
        assertType(Locale::class, $request->route('locale'));

        // A nullable bind return type is narrowed to the non-null model.
        assertType(Playlist::class, $request->route('playlist'));

        // A bind without a return-type hint is not narrowed — default type stands.
        assertType('object|string|null', $request->route('dynamic'));

        // An unknown parameter is not narrowed.
        assertType('object|string|null', $request->route('unknown'));

        // With a default argument the bound model isn't guaranteed (Laravel returns the default when
        // the parameter is missing), so it is not narrowed.
        assertType('object|string|null', $request->route('video', 'fallback'));

        // A Route::model binding with a missing-model callback is skipped (callback can replace it).
        assertType('object|string|null', $request->route('guarded'));

        // A binding declared in a provider's inherited boot() (in a base-class file) is found.
        assertType(Locale::class, $request->route('inherited'));

        // No argument returns the Route object — left untouched.
        assertType(Route::class, $request->route());

        // A dynamic (non-constant) parameter name is not narrowed.
        $name = $this->name();
        assertType('object|string|null', $request->route($name));
    }

    public function viaFormRequest(RouteBindingFormRequest $request): void
    {
        // The instance method on a FormRequest (a Request subclass) is narrowed too.
        assertType(Video::class, $request->route('video'));
    }

    private function name(): string
    {
        return 'video';
    }
}

final class RouteBindingFormRequest extends FormRequest {}
