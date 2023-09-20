<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => 'test');
Route::get('', fn () => 'test'); // Expecting: A route URL should be / instead of an empty string.
Route::post('', fn () => 'test'); // Expecting: A route URL should be / instead of an empty string.

Route::middleware('web')->group(function () {
    Route::get('', fn () => 'test'); // Expecting: A route URL should be / instead of an empty string.
});

Route::get('about', fn () => 'test');
Route::get('/about', fn () => 'test'); // Expecting: A route URL should not start or end with /.
Route::get('/home/about/', fn () => 'test'); // Expecting: A route URL should not start or end with /.
Route::get('about/', fn () => 'test'); // Expecting: A route URL should not start or end with /.
