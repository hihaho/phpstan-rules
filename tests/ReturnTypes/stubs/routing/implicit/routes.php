<?php declare(strict_types=1);

use Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\AdaptiveSubjectController;
use Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\AppleController;
use Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\BananaController;
use Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\ContainerController;
use Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\UnhintedController;
use Hihaho\PhpstanRules\Tests\ReturnTypes\stubs\routing\implicit\VideoController;
use Illuminate\Support\Facades\Route;

// Invokable single-action controller (__invoke); snake_case route param ↔ camelCase method param.
Route::patch('video/{video}/subjects/{adaptive_learning_subject}', AdaptiveSubjectController::class);

// Array action [Controller::class, 'method'].
Route::get('containers/{videoContainer}', [ContainerController::class, 'show']);

// `match` puts the methods first, so the URI/action are shifted by one.
Route::match(['get', 'post'], 'videos/{video}/edit', [VideoController::class, 'edit']);

// Same route parameter bound to different models across routes — route('item') is their union.
Route::get('apples/{item}', AppleController::class);
Route::get('bananas/{item}', BananaController::class);

// A closure action and a non-model type-hint are both skipped (not narrowed).
Route::get('ping/{ping}', fn () => 'pong');
Route::get('loose/{loose}', UnhintedController::class);
