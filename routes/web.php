<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/blizzard/redirect', [AuthController::class, 'redirect']);
Route::get('/auth/blizzard/callback', [AuthController::class, 'callback']);

Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('/sitemap-pages.xml', [SeoController::class, 'sitemapPages']);
Route::get('/sitemap-characters.xml', [SeoController::class, 'sitemapCharacters']);
Route::get('/sitemap-database.xml', [DatabaseController::class, 'sitemap']);

Route::get('/character/{realm}/{name}', [SeoController::class, 'characterPage']);

Route::get('/base-de-donnees', [DatabaseController::class, 'index']);
Route::get('/base-de-donnees/montures/{category?}', [DatabaseController::class, 'mounts']);
Route::get('/base-de-donnees/hauts-faits/{expansion?}', [DatabaseController::class, 'achievements']);
Route::get('/base-de-donnees/quetes/{expansion?}', [DatabaseController::class, 'quests']);
Route::get('/base-de-donnees/mascottes/{category?}', [DatabaseController::class, 'pets']);
Route::get('/base-de-donnees/decorations/{category?}', [DatabaseController::class, 'decors']);
Route::get('/base-de-donnees/professions/{profession?}', [DatabaseController::class, 'professions']);

Route::get('/faq', [SeoController::class, 'faqPage']);
Route::get('/cgu', [SeoController::class, 'cguPage']);
Route::get('/privacy', [SeoController::class, 'privacyPage']);

if (app()->isLocal()) {
    Route::get('/docs', [DocsController::class, 'index']);
    Route::get('/docs/{path}', [DocsController::class, 'file'])->where('path', '.+\.md');
}

Route::get('/{any?}', [SeoController::class, 'spa'])->where('any', '^(?!api/|docs/).*$');
