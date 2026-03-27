<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\DatabaseSeoService;
use App\Application\Services\SeoContentRenderer;
use Illuminate\Contracts\View\View;

class DatabaseController extends Controller
{
    public function __construct(
        private readonly DatabaseSeoService $databaseSeoService,
        private readonly SeoContentRenderer $seoContentRenderer,
    ) {}

    public function index(): View
    {
        $seo = $this->databaseSeoService->getIndexMeta();
        $seo['serverHtml'] = $this->seoContentRenderer->renderDatabaseIndex($this->appUrl());

        return view('welcome', ['seo' => $seo]);
    }

    public function mounts(?string $category = null): View
    {
        $seo = $this->databaseSeoService->getMountsMeta($category);

        abort_if($seo === null, 404);

        $seo['serverHtml'] = $this->seoContentRenderer->renderMounts($this->appUrl(), $category) ?? '';

        return view('welcome', ['seo' => $seo]);
    }

    public function achievements(?string $expansion = null): View
    {
        $seo = $this->databaseSeoService->getAchievementsMeta($expansion);

        abort_if($seo === null, 404);

        $seo['serverHtml'] = $this->seoContentRenderer->renderAchievements($this->appUrl(), $expansion) ?? '';

        return view('welcome', ['seo' => $seo]);
    }

    public function quests(?string $expansion = null, ?string $zone = null): View
    {
        $seo = $this->databaseSeoService->getQuestsMeta($expansion, $zone);

        abort_if($seo === null, 404);

        $seo['serverHtml'] = $this->seoContentRenderer->renderQuests($this->appUrl(), $expansion, $zone) ?? '';

        return view('welcome', ['seo' => $seo]);
    }

    public function pets(?string $category = null): View
    {
        $seo = $this->databaseSeoService->getPetsMeta($category);

        abort_if($seo === null, 404);

        $seo['serverHtml'] = $this->seoContentRenderer->renderPets($this->appUrl(), $category) ?? '';

        return view('welcome', ['seo' => $seo]);
    }

    public function decors(?string $category = null): View
    {
        $seo = $this->databaseSeoService->getDecorsMeta($category);

        abort_if($seo === null, 404);

        $seo['serverHtml'] = $this->seoContentRenderer->renderDecors($this->appUrl(), $category) ?? '';

        return view('welcome', ['seo' => $seo]);
    }

    public function professions(?string $profession = null): View
    {
        $seo = $this->databaseSeoService->getProfessionsMeta($profession);

        abort_if($seo === null, 404);

        $seo['serverHtml'] = $this->seoContentRenderer->renderProfessions($this->appUrl(), $profession) ?? '';

        return view('welcome', ['seo' => $seo]);
    }

    private function appUrl(): string
    {
        /** @var string $configUrl */
        $configUrl = config('app.url', '');

        return rtrim($configUrl, '/');
    }
}
