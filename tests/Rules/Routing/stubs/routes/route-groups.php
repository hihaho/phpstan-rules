<?php

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
