<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::put('/users', fn () => 'test');
Route::patch('/users', fn () => 'test');
Route::delete('/users', fn () => 'test');
Route::any('/users', fn () => 'test');
Route::head('/users', fn () => 'test');
