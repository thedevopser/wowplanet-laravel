<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/blizzard/redirect', [AuthController::class, 'redirect']);
Route::get('/auth/blizzard/callback', [AuthController::class, 'callback']);

Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('/sitemap-pages.xml', [SeoController::class, 'sitemapPages']);
Route::get('/sitemap-database.xml', [DatabaseController::class, 'sitemap']);

Route::get('/', [SeoController::class, 'home']);

Route::get('/character/{realm}/{name}', [CharacterController::class, 'page']);

Route::get('/my-characters', [AccountController::class, 'myCharacters']);
Route::get('/class-stats', [AccountController::class, 'classStats']);
Route::get('/my-score', [AccountController::class, 'myScore']);

Route::get('/admin', [AdminController::class, 'page']);

Route::get('/base-de-donnees', [DatabaseController::class, 'index']);
Route::get('/base-de-donnees/montures/{category?}', [DatabaseController::class, 'mounts']);
Route::get('/base-de-donnees/hauts-faits/{expansion?}', [DatabaseController::class, 'achievements']);
Route::get('/base-de-donnees/quetes/{expansion?}', [DatabaseController::class, 'quests']);
Route::get('/base-de-donnees/mascottes/{category?}', [DatabaseController::class, 'pets']);
Route::get('/base-de-donnees/decorations/{category?}', [DatabaseController::class, 'decors']);
Route::get('/base-de-donnees/garde-robe/{slot?}', [DatabaseController::class, 'appearances']);
Route::get('/base-de-donnees/professions/{profession?}', [DatabaseController::class, 'professions']);

Route::get('/faq', [SeoController::class, 'faqPage']);
Route::get('/cgu', [SeoController::class, 'cguPage']);
Route::get('/privacy', [SeoController::class, 'privacyPage']);
Route::get('/addons', [SeoController::class, 'addonsPage']);

if (app()->isLocal()) {
    Route::get('/docs', [DocsController::class, 'index']);
    Route::get('/docs/{path}', [DocsController::class, 'file'])->where('path', '.+\.md');
}

// Catch-all : toute URL inconnue (hors api/ et docs/) rend la page 404 Inertia.
Route::get('/{any}', [SeoController::class, 'notFound'])->where('any', '^(?!api/|docs/).*$');
