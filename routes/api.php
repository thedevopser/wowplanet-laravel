<?php

use App\Http\Controllers\CharacterController;
use App\Http\Controllers\DatabaseApiController;
use App\Http\Controllers\UserCharacterController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function (): void {
    Route::get('/character/{realm}/{name}', [CharacterController::class, 'show']);

    Route::get('/database/counts', [DatabaseApiController::class, 'counts']);
    Route::get('/database/mounts', [DatabaseApiController::class, 'mounts']);
    Route::get('/database/achievements', [DatabaseApiController::class, 'achievements']);
    Route::get('/database/quests', [DatabaseApiController::class, 'quests']);
    Route::get('/database/pets', [DatabaseApiController::class, 'pets']);
    Route::get('/database/decors', [DatabaseApiController::class, 'decors']);
    Route::get('/database/professions', [DatabaseApiController::class, 'professions']);
    Route::get('/database/professions/recipes', [DatabaseApiController::class, 'professionRecipes']);
});

Route::middleware('throttle:authenticated')->group(function (): void {
    Route::get('/auth/status', [UserCharacterController::class, 'authStatus']);
    Route::post('/auth/logout', [UserCharacterController::class, 'logout']);
    Route::get('/user/characters', [UserCharacterController::class, 'index']);
    Route::get('/class-icons', [UserCharacterController::class, 'classIcons']);
    Route::get('/account/score', [UserCharacterController::class, 'accountScore']);
    Route::post('/account/score/refresh', [UserCharacterController::class, 'refreshAccountScore']);
});
