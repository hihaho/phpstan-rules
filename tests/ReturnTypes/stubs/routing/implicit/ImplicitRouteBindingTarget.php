<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit;

use Illuminate\Http\Request;

use function PHPStan\Testing\assertType;

final class ImplicitRouteBindingTarget
{
    public function exercise(Request $request): void
    {
        // Invokable controller __invoke param type-hint.
        assertType(Video::class, $request->route('video'));

        // snake_case route param resolved against the camelCase method param (Laravel's Str::snake rule).
        assertType(AdaptiveLearningSubject::class, $request->route('adaptive_learning_subject'));

        // [Controller::class, 'method'] array action.
        assertType(VideoContainer::class, $request->route('videoContainer'));

        // Same param bound to different models across routes → the union.
        assertType('Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\Apple|Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\Banana', $request->route('item'));

        // The skip/fail-safe cases (closure action, non-model hint, unknown parameter) assert the
        // default route() type, whose larastan formatting varies by version — covered in the
        // Laravel-only ImplicitRouteBindingStaticTarget instead, with a stable type string.
    }
}
