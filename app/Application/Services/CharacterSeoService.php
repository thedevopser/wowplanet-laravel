<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Models\CharacterVisit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CharacterSeoService
{
    public function __construct(
        private BlizzardApiClient $apiClient,
    ) {}

    public function getHomeMeta(): array
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        return [
            'title' => 'WowPlanet - Suivi de progression World of Warcraft',
            'description' => 'Analysez votre personnage World of Warcraft : quêtes, hauts-faits, montures et mascottes. Comparez votre progression avec la base de données complète du jeu.',
            'ogTitle' => 'WowPlanet - Suivi de progression World of Warcraft',
            'ogDescription' => 'Suivez la progression de vos personnages WoW. Plus de 21 000 quêtes, 8 600 hauts-faits, 1 569 montures et 2 117 mascottes référencées.',
            'ogImage' => $appUrl . '/images/og-default.png',
            'ogUrl' => $appUrl,
            'ogType' => 'website',
            'canonicalUrl' => $appUrl,
            'jsonLd' => $this->getHomeJsonLd($appUrl),
        ];
    }

    public function getCharacterMeta(string $realm, string $name): array
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $realmSlug = strtolower($realm);
        $nameSlug = strtolower($name);
        $cacheKey = "seo_character_{$realmSlug}_{$nameSlug}";

        $charData = Cache::remember($cacheKey, 3600, function () use ($realmSlug, $nameSlug, $name, $realm) {
            try {
                $summary = $this->apiClient->get(
                    "profile/wow/character/{$realmSlug}/{$nameSlug}"
                );

                $media = $this->apiClient->get(
                    "profile/wow/character/{$realmSlug}/{$nameSlug}/character-media"
                );

                $avatarUrl = $media['assets'][1]['value']
                    ?? $media['assets'][0]['value']
                    ?? '';

                return [
                    'name' => $summary['name'] ?? ucfirst($name),
                    'realm' => $summary['realm']['name'] ?? ucfirst($realm),
                    'level' => $summary['level'] ?? 0,
                    'race' => $summary['race']['name'] ?? '',
                    'class' => $summary['character_class']['name'] ?? '',
                    'faction' => $summary['faction']['name'] ?? '',
                    'ilvl' => $summary['equipped_item_level'] ?? 0,
                    'avatarUrl' => $avatarUrl,
                    'found' => true,
                ];
            } catch (\Exception $e) {
                Log::warning("SEO: Failed to fetch character {$realm}/{$name}: " . $e->getMessage());

                return [
                    'name' => ucfirst($name),
                    'realm' => ucfirst($realm),
                    'found' => false,
                ];
            }
        });

        $this->trackCharacterVisit($realmSlug, $nameSlug, $charData);

        $canonicalUrl = $appUrl . '/character/' . $realmSlug . '/' . $nameSlug;

        if ($charData['found']) {
            $title = "{$charData['name']} - {$charData['class']} {$charData['level']} | {$charData['realm']} | WowPlanet";
            $description = "{$charData['name']}, {$charData['race']} {$charData['class']} niveau {$charData['level']} (ilvl {$charData['ilvl']}) sur {$charData['realm']} ({$charData['faction']}). Consultez sa progression : quêtes, hauts-faits, montures et mascottes.";
            $ogImage = $charData['avatarUrl'] ?: ($appUrl . '/images/og-default.png');
        } else {
            $title = "{$charData['name']} - {$charData['realm']} | WowPlanet";
            $description = "Profil du personnage {$charData['name']} sur {$charData['realm']}. Consultez sa progression World of Warcraft sur WowPlanet.";
            $ogImage = $appUrl . '/images/og-default.png';
        }

        return [
            'title' => $title,
            'description' => $description,
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $ogImage,
            'ogUrl' => $canonicalUrl,
            'ogType' => 'profile',
            'canonicalUrl' => $canonicalUrl,
            'jsonLd' => $charData['found']
                ? $this->getCharacterJsonLd($charData, $canonicalUrl)
                : null,
        ];
    }

    public function generateSitemap(): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        $urls = [];

        $urls[] = [
            'loc' => $appUrl,
            'changefreq' => 'weekly',
            'priority' => '1.0',
        ];

        $recentVisits = CharacterVisit::where('last_visited_at', '>=', now()->subDays(30))
            ->orderBy('last_visited_at', 'desc')
            ->limit(1000)
            ->get();

        foreach ($recentVisits as $visit) {
            $urls[] = [
                'loc' => $appUrl . '/character/' . $visit->realm_slug . '/' . $visit->character_name,
                'lastmod' => $visit->last_visited_at->toW3cString(),
                'changefreq' => 'daily',
                'priority' => '0.8',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($url['loc']) . "</loc>\n";
            if (isset($url['lastmod'])) {
                $xml .= "    <lastmod>{$url['lastmod']}</lastmod>\n";
            }
            $xml .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$url['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    private function trackCharacterVisit(string $realmSlug, string $nameSlug, array $charData): void
    {
        try {
            CharacterVisit::updateOrCreate(
                [
                    'realm_slug' => $realmSlug,
                    'character_name' => $nameSlug,
                ],
                [
                    'display_name' => $charData['name'] ?? ucfirst($nameSlug),
                    'display_realm' => $charData['realm'] ?? ucfirst($realmSlug),
                    'class_name' => $charData['class'] ?? null,
                    'level' => $charData['level'] ?? null,
                    'last_visited_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::debug("SEO: Failed to track visit for {$realmSlug}/{$nameSlug}: " . $e->getMessage());
        }
    }

    private function getHomeJsonLd(string $appUrl): string
    {
        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebApplication',
            'name' => 'WowPlanet',
            'url' => $appUrl,
            'description' => 'Suivez la progression de vos personnages World of Warcraft : quêtes, hauts-faits, montures et mascottes.',
            'applicationCategory' => 'GameApplication',
            'operatingSystem' => 'Web',
            'inLanguage' => 'fr',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'EUR',
            ],
            'about' => [
                '@type' => 'VideoGame',
                'name' => 'World of Warcraft',
                'gamePlatform' => 'PC',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function getCharacterJsonLd(array $charData, string $url): string
    {
        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            'name' => "{$charData['name']} - WowPlanet",
            'url' => $url,
            'mainEntity' => [
                '@type' => 'Person',
                'name' => $charData['name'],
                'description' => "{$charData['race']} {$charData['class']} niveau {$charData['level']} sur {$charData['realm']}",
                'image' => $charData['avatarUrl'] ?: null,
            ],
            'isPartOf' => [
                '@type' => 'WebApplication',
                'name' => 'WowPlanet',
            ],
            'about' => [
                '@type' => 'VideoGame',
                'name' => 'World of Warcraft',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
