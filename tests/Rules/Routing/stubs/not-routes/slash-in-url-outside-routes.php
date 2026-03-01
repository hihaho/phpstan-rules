<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('', fn () => 'test');
Route::get('/about', fn () => 'test');
