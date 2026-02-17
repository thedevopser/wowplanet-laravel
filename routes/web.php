<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/blizzard/redirect', [AuthController::class, 'redirect']);
Route::get('/auth/blizzard/callback', [AuthController::class, 'callback']);
