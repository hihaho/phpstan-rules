<?php declare(strict_types=1);

namespace Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit;

use Illuminate\Http\Request;

use function PHPStan\Testing\assertType;

final class PrecedenceTarget
{
    public function exercise(Request $request): void
    {
        // {video} is bound explicitly (Route::model → VideoContainer) and implicitly (route file → Video).
        // The explicit binding wins.
        assertType('Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\VideoContainer', $request->route('video'));

        // A parameter with only an implicit binding still resolves through the fallback.
        assertType('Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\AdaptiveLearningSubject', $request->route('adaptive_learning_subject'));
    }
}
