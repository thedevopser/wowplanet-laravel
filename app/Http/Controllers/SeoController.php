<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CharacterSeoService;
use App\Application\Services\SeoContentRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

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

    public function faqPage(): InertiaResponse
    {
        return Inertia::render('FaqPage', [
            'meta' => $this->characterSeoService->getStaticPageMeta('faq'),
        ]);
    }

    public function cguPage(): InertiaResponse
    {
        return Inertia::render('CguPage', [
            'meta' => $this->characterSeoService->getStaticPageMeta('cgu'),
        ]);
    }

    public function privacyPage(): InertiaResponse
    {
        return Inertia::render('PrivacyPage', [
            'meta' => $this->characterSeoService->getStaticPageMeta('privacy'),
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
            'Allow: /character/',
            'Disallow: /api/',
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
