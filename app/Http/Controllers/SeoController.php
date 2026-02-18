<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CharacterSeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SeoController extends Controller
{
    public function __construct(
        private CharacterSeoService $seoService,
    ) {}

    public function spa(): View
    {
        return view('welcome', [
            'seo' => $this->seoService->getHomeMeta(),
        ]);
    }

    public function characterPage(string $realm, string $name): View|RedirectResponse
    {
        $normalizedRealm = strtolower($realm);
        $normalizedName = strtolower($name);

        if ($realm !== $normalizedRealm || $name !== $normalizedName) {
            return redirect("/character/{$normalizedRealm}/{$normalizedName}", 301);
        }

        return view('welcome', [
            'seo' => $this->seoService->getCharacterMeta($realm, $name),
        ]);
    }

    public function sitemap(): Response
    {
        $xml = $this->seoService->generateSitemap();

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    public function robots(): Response
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /api/',
            'Disallow: /auth/',
            'Disallow: /my-characters',
            'Disallow: /class-stats',
            '',
            "Sitemap: {$appUrl}/sitemap.xml",
            '',
        ]);

        return response($content, 200, [
            'Content-Type' => 'text/plain',
        ]);
    }
}
