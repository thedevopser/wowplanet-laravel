<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CharacterSeoService;
use App\Application\Services\SeoContentRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function __construct(
        private readonly CharacterSeoService $characterSeoService,
        private readonly SeoContentRenderer $seoContentRenderer,
    ) {}

    public function spa(): View
    {
        $seo = $this->characterSeoService->getHomeMeta();
        $seo['serverHtml'] = $this->seoContentRenderer->renderHome($this->appUrl());

        return view('welcome', ['seo' => $seo]);
    }

    public function faqPage(): View
    {
        $seo = $this->characterSeoService->getStaticPageMeta('faq');
        $seo['serverHtml'] = '';

        return view('welcome', ['seo' => $seo]);
    }

    public function cguPage(): View
    {
        $seo = $this->characterSeoService->getStaticPageMeta('cgu');
        $seo['serverHtml'] = '';

        return view('welcome', ['seo' => $seo]);
    }

    public function privacyPage(): View
    {
        $seo = $this->characterSeoService->getStaticPageMeta('privacy');
        $seo['serverHtml'] = '';

        return view('welcome', ['seo' => $seo]);
    }

    public function characterPage(string $realm, string $name): View|RedirectResponse
    {
        $normalizedRealm = mb_strtolower($realm);
        $normalizedName = mb_strtolower($name);

        if ($realm !== $normalizedRealm || $name !== $normalizedName) {
            return redirect(sprintf('/character/%s/%s', $normalizedRealm, $normalizedName), 301);
        }

        $seo = $this->characterSeoService->getCharacterMeta($realm, $name);
        $seo['serverHtml'] = $this->seoContentRenderer->renderCharacter(
            $this->appUrl(),
            $this->characterSeoService->getCachedCharacterData($realm, $name),
            $realm,
            $name,
        );

        return view('welcome', ['seo' => $seo]);
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
            'Disallow: /character/',
            'Disallow: /auth/',
            'Disallow: /admin',
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

    private function appUrl(): string
    {
        /** @var string $configUrl */
        $configUrl = config('app.url', '');

        return rtrim($configUrl, '/');
    }
}
