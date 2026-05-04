<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\DatabaseContentRenderer;
use App\Application\Services\DatabaseSeoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class DatabaseController extends Controller
{
    public function __construct(
        private readonly DatabaseSeoService $databaseSeoService,
        private readonly DatabaseContentRenderer $databaseContentRenderer,
    ) {}

    public function index(): View
    {
        $seo = $this->databaseSeoService->getIndexMeta();
        $seo['serverHtml'] = $this->databaseContentRenderer->renderDatabaseIndex($this->appUrl());

        return view('welcome', ['seo' => $seo]);
    }

    public function mounts(?string $category = null): View|RedirectResponse
    {
        $seo = $this->databaseSeoService->getMountsMeta($category);

        if ($seo === null) {
            return redirect('/base-de-donnees/montures', 301);
        }

        $seo['serverHtml'] = $this->databaseContentRenderer->renderMounts($this->appUrl(), $category) ?? '';

        return view('welcome', ['seo' => $seo]);
    }

    public function achievements(?string $expansion = null): View|RedirectResponse
    {
        $seo = $this->databaseSeoService->getAchievementsMeta($expansion);

        if ($seo === null) {
            return redirect('/base-de-donnees/hauts-faits', 301);
        }

        $seo['serverHtml'] = $this->databaseContentRenderer->renderAchievements($this->appUrl(), $expansion) ?? '';

        return view('welcome', ['seo' => $seo]);
    }

    public function quests(?string $expansion = null): View|RedirectResponse
    {
        $seo = $this->databaseSeoService->getQuestsMeta($expansion);

        if ($seo === null) {
            return redirect('/base-de-donnees/quetes', 301);
        }

        $seo['serverHtml'] = $this->databaseContentRenderer->renderQuests($this->appUrl(), $expansion) ?? '';

        return view('welcome', ['seo' => $seo]);
    }

    public function pets(?string $category = null): View|RedirectResponse
    {
        $seo = $this->databaseSeoService->getPetsMeta($category);

        if ($seo === null) {
            return redirect('/base-de-donnees/mascottes', 301);
        }

        $seo['serverHtml'] = $this->databaseContentRenderer->renderPets($this->appUrl(), $category) ?? '';

        return view('welcome', ['seo' => $seo]);
    }

    public function decors(?string $category = null): View|RedirectResponse
    {
        $seo = $this->databaseSeoService->getDecorsMeta($category);

        if ($seo === null) {
            return redirect('/base-de-donnees/decorations', 301);
        }

        $seo['serverHtml'] = $this->databaseContentRenderer->renderDecors($this->appUrl(), $category) ?? '';

        return view('welcome', ['seo' => $seo]);
    }

    public function professions(?string $profession = null): View|RedirectResponse
    {
        $seo = $this->databaseSeoService->getProfessionsMeta($profession);

        if ($seo === null) {
            return redirect('/base-de-donnees/professions', 301);
        }

        $seo['serverHtml'] = $this->databaseContentRenderer->renderProfessions($this->appUrl(), $profession) ?? '';

        return view('welcome', ['seo' => $seo]);
    }

    public function sitemap(): \Illuminate\Http\Response
    {
        $xml = $this->databaseSeoService->generateSitemap();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    private function appUrl(): string
    {
        /** @var string $configUrl */
        $configUrl = config('app.url', '');

        return rtrim($configUrl, '/');
    }
}
