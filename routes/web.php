<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/blizzard/redirect', [AuthController::class, 'redirect']);
Route::get('/auth/blizzard/callback', [AuthController::class, 'callback']);

Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);

Route::get('/character/{realm}/{name}', [SeoController::class, 'characterPage']);

Route::get('/{any?}', [SeoController::class, 'spa'])->where('any', '^(?!api/).*$');
