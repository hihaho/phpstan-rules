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

        // A closure action, a non-model type-hint, and an unknown parameter are not narrowed.
        // (Under larastan the default route() type prints as the parenthesised benevolent union.)
        assertType('(object|string|null)', $request->route('ping'));
        assertType('(object|string|null)', $request->route('loose'));
        assertType('(object|string|null)', $request->route('unknown'));
    }
}
