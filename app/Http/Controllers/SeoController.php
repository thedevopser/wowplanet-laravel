<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CharacterSeoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function __construct(
        private readonly CharacterSeoService $characterSeoService,
    ) {}

    public function spa(): View
    {
        return view('welcome', [
            'seo' => $this->characterSeoService->getHomeMeta(),
        ]);
    }

    public function characterPage(string $realm, string $name): View|RedirectResponse
    {
        $normalizedRealm = mb_strtolower($realm);
        $normalizedName = mb_strtolower($name);

        if ($realm !== $normalizedRealm || $name !== $normalizedName) {
            return redirect(sprintf('/character/%s/%s', $normalizedRealm, $normalizedName), 301);
        }

        return view('welcome', [
            'seo' => $this->characterSeoService->getCharacterMeta($realm, $name),
        ]);
    }

    public function sitemap(): Response
    {
        $xml = $this->characterSeoService->generateSitemapIndex();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function sitemapPages(): Response
    {
        $xml = $this->characterSeoService->generatePagesSitemap();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function sitemapCharacters(): Response
    {
        $xml = $this->characterSeoService->generateCharactersSitemap();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(): Response
    {
        /** @var string $configUrl */
        $configUrl = config('app.url', '');
        $appUrl = rtrim($configUrl, '/');

        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Allow: /base-de-donnees/',
            'Disallow: /api/',
            'Disallow: /auth/',
            'Disallow: /my-characters',
            'Disallow: /my-score',
            'Disallow: /class-stats',
            '',
            sprintf('Sitemap: %s/sitemap.xml', $appUrl),
            '',
        ]);

        return response($content, 200, [
            'Content-Type' => 'text/plain',
        ]);
    }
}
