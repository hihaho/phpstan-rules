<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit;

use Illuminate\Http\Request;

use function PHPStan\Testing\assertType;

/**
 * The same implicit-binding resolution without larastan, so the skip cases assert a stable default
 * route() type string (larastan's benevolent-union formatting varies by version).
 */
final class ImplicitRouteBindingStaticTarget
{
    public function exercise(Request $request): void
    {
        assertType('Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\Video', $request->route('video'));
        assertType('Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\AdaptiveLearningSubject', $request->route('adaptive_learning_subject'));
        assertType('Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\VideoContainer', $request->route('videoContainer'));
        assertType('Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\Apple|Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\Banana', $request->route('item'));

        // A closure action, a non-model type-hint, and an unknown parameter are not narrowed.
        assertType('object|string|null', $request->route('ping'));
        assertType('object|string|null', $request->route('loose'));
        assertType('object|string|null', $request->route('unknown'));
    }
}
