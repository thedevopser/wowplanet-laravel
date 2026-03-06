<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\DatabaseSeoService;
use Illuminate\Contracts\View\View;

class DatabaseController extends Controller
{
    public function __construct(
        private readonly DatabaseSeoService $databaseSeoService,
    ) {}

    public function index(): View
    {
        return view('welcome', [
            'seo' => $this->databaseSeoService->getIndexMeta(),
        ]);
    }

    public function mounts(?string $category = null): View
    {
        return view('welcome', [
            'seo' => $this->databaseSeoService->getMountsMeta($category),
        ]);
    }

    public function achievements(?string $expansion = null): View
    {
        return view('welcome', [
            'seo' => $this->databaseSeoService->getAchievementsMeta($expansion),
        ]);
    }

    public function quests(?string $expansion = null, ?string $zone = null): View
    {
        return view('welcome', [
            'seo' => $this->databaseSeoService->getQuestsMeta($expansion, $zone),
        ]);
    }

    public function pets(?string $category = null): View
    {
        return view('welcome', [
            'seo' => $this->databaseSeoService->getPetsMeta($category),
        ]);
    }

    public function decors(?string $category = null): View
    {
        return view('welcome', [
            'seo' => $this->databaseSeoService->getDecorsMeta($category),
        ]);
    }

    public function professions(?string $profession = null): View
    {
        return view('welcome', [
            'seo' => $this->databaseSeoService->getProfessionsMeta($profession),
        ]);
    }
}
