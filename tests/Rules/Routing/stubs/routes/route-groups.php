<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::name('')->group(function (): void {
    //
});

Route::group([
    'middleware' => 'openSource',
    'name' => 'open-source',
], function (): void {
    //
});
