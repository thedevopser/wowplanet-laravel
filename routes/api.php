<?php

use App\Http\Controllers\CharacterController;
use App\Http\Controllers\UserCharacterController;
use Illuminate\Support\Facades\Route;

Route::get('/character/{realm}/{name}', [CharacterController::class, 'show']);

Route::get('/auth/status', [UserCharacterController::class, 'authStatus']);
Route::post('/auth/logout', [UserCharacterController::class, 'logout']);
Route::get('/user/characters', [UserCharacterController::class, 'index']);
Route::get('/class-icons', [UserCharacterController::class, 'classIcons']);
