<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Models\CharacterVisit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class CharacterSeoService
{
    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function getHomeMeta(): array
    {
        /** @var string $configUrl */
        $configUrl = config('app.url', '');
        $appUrl = rtrim($configUrl, '/');

        return [
            'title' => 'WowPlanet - Suivi de progression World of Warcraft',
            'description' => 'Analysez votre personnage World of Warcraft : quêtes, hauts-faits, montures et mascottes.'
                .' Comparez votre progression avec la base de données complète du jeu.',
            'ogTitle' => 'WowPlanet - Suivi de progression World of Warcraft',
            'ogDescription' => 'Suivez la progression de vos personnages WoW.'
                .' Plus de 21 000 quêtes, 8 600 hauts-faits, 1 569 montures et 2 117 mascottes référencées.',
            'ogImage' => $appUrl.'/images/og-default.png',
            'ogUrl' => $appUrl,
            'ogType' => 'website',
            'canonicalUrl' => $appUrl,
            'jsonLd' => $this->getHomeJsonLd($appUrl),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function getCharacterMeta(string $realm, string $name): array
    {
        /** @var string $configUrl */
        $configUrl = config('app.url', '');
        $appUrl = rtrim($configUrl, '/');
        $realmSlug = strtolower($realm);
        $nameSlug = strtolower($name);
        $cacheKey = sprintf('seo_character_%s_%s', $realmSlug, $nameSlug);

        /** @var array<string, string|int|bool> $charData */
        $charData = Cache::remember(
            $cacheKey,
            3600,
            function () use ($realmSlug, $nameSlug, $name, $realm): array {
                try {
                    $summary = $this->blizzardApiClient->get(
                        sprintf('profile/wow/character/%s/%s', $realmSlug, $nameSlug),
                    );

                    $media = $this->blizzardApiClient->get(
                        sprintf(
                            'profile/wow/character/%s/%s/character-media',
                            $realmSlug,
                            $nameSlug,
                        ),
                    );

                    /** @var list<array{key: string, value: string}> $mediaAssets */
                    $mediaAssets = $media['assets'] ?? [];
                    $avatarUrl = (string) ($mediaAssets[1]['value']
                        ?? $mediaAssets[0]['value']
                        ?? '');

                    /** @var array{name?: string} $realmData */
                    $realmData = $summary['realm'] ?? [];
                    /** @var array{name?: string} $raceData */
                    $raceData = $summary['race'] ?? [];
                    /** @var array{name?: string} $classData */
                    $classData = $summary['character_class'] ?? [];
                    /** @var array{name?: string} $factionData */
                    $factionData = $summary['faction'] ?? [];

                    return [
                        'name' => is_string($summary['name'] ?? null)
                            ? $summary['name'] : ucfirst($name),
                        'realm' => (string) ($realmData['name'] ?? ucfirst($realm)),
                        'level' => is_int($summary['level'] ?? null)
                            ? $summary['level'] : 0,
                        'race' => (string) ($raceData['name'] ?? ''),
                        'class' => (string) ($classData['name'] ?? ''),
                        'faction' => (string) ($factionData['name'] ?? ''),
                        'ilvl' => is_int($summary['equipped_item_level'] ?? null)
                            ? $summary['equipped_item_level'] : 0,
                        'avatarUrl' => $avatarUrl,
                        'found' => true,
                    ];
                } catch (\Exception $exception) {
                    Log::warning(
                        sprintf('SEO: Failed to fetch character %s/%s: ', $realm, $name)
                        .$exception->getMessage(),
                    );

                    return [
                        'name' => ucfirst($name),
                        'realm' => ucfirst($realm),
                        'found' => false,
                    ];
                }
            },
        );

        $this->trackCharacterVisit($realmSlug, $nameSlug, $charData);

        $canonicalUrl = $appUrl.'/character/'.$realmSlug.'/'.$nameSlug;

        $title = sprintf(
            '%s - %s | WowPlanet',
            (string) $charData['name'],
            (string) $charData['realm'],
        );
        $description = sprintf(
            'Profil du personnage %s sur %s.'
            .' Consultez sa progression World of Warcraft sur WowPlanet.',
            (string) $charData['name'],
            (string) $charData['realm'],
        );
        $ogImage = $appUrl.'/images/og-default.png';

        if (! empty($charData['found'])) {
            $title = sprintf(
                '%s - %s %s | %s | WowPlanet',
                (string) $charData['name'],
                (string) ($charData['class'] ?? ''),
                (string) ($charData['level'] ?? ''),
                (string) $charData['realm'],
            );
            $description = sprintf(
                '%s, %s %s niveau %s (ilvl %s) sur %s (%s).'
                .' Consultez sa progression : quêtes, hauts-faits, montures et mascottes.',
                (string) $charData['name'],
                (string) ($charData['race'] ?? ''),
                (string) ($charData['class'] ?? ''),
                (string) ($charData['level'] ?? ''),
                (string) ($charData['ilvl'] ?? ''),
                (string) $charData['realm'],
                (string) ($charData['faction'] ?? ''),
            );
            $ogImage = ((string) ($charData['avatarUrl'] ?? '')) ?: ($appUrl.'/images/og-default.png');
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
            'jsonLd' => empty($charData['found'])
                ? null
                : $this->getCharacterJsonLd($charData, $canonicalUrl),
        ];
    }

    public function generateSitemap(): string
    {
        /** @var string $xml */
        $xml = Cache::remember('sitemap_xml', 3600, fn (): string => $this->buildSitemap()->render());

        return $xml;
    }

    private function buildSitemap(): Sitemap
    {
        /** @var string $configUrl */
        $configUrl = config('app.url', '');
        $appUrl = rtrim($configUrl, '/');

        $sitemap = Sitemap::create()
            ->add(
                Url::create($appUrl)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(1.0),
            );

        /** @var \Illuminate\Database\Eloquent\Collection<int, CharacterVisit> $recentVisits */
        $recentVisits = CharacterVisit::query()
            ->where('last_visited_at', '>=', now()->subDays(30))
            ->latest('last_visited_at')
            ->limit(1000)
            ->get();

        foreach ($recentVisits as $recentVisit) {
            /** @var \Carbon\Carbon $lastVisitedAt */
            $lastVisitedAt = $recentVisit->last_visited_at;
            $sitemap->add(
                Url::create($appUrl.'/character/'.$recentVisit->realm_slug.'/'.$recentVisit->character_name)
                    ->setLastModificationDate($lastVisitedAt)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                    ->setPriority(0.8),
            );
        }

        return $sitemap;
    }

    /**
     * @param  array<string, string|int|bool>  $charData
     */
    private function trackCharacterVisit(
        string $realmSlug,
        string $nameSlug,
        array $charData,
    ): void {
        try {
            CharacterVisit::query()->updateOrCreate([
                'realm_slug' => $realmSlug,
                'character_name' => $nameSlug,
            ], [
                'display_name' => (string) ($charData['name'] ?? ucfirst($nameSlug)),
                'display_realm' => (string) ($charData['realm'] ?? ucfirst($realmSlug)),
                'class_name' => (string) ($charData['class'] ?? ''),
                'level' => (int) ($charData['level'] ?? 0),
                'last_visited_at' => now(),
            ]);
        } catch (\Exception $exception) {
            Log::debug(
                sprintf('SEO: Failed to track visit for %s/%s: ', $realmSlug, $nameSlug)
                .$exception->getMessage(),
            );
        }
    }

    private function getHomeJsonLd(string $appUrl): string
    {
        return (string) json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebApplication',
            'name' => 'WowPlanet',
            'url' => $appUrl,
            'description' => 'Suivez la progression de vos personnages World of Warcraft'
                .' : quêtes, hauts-faits, montures et mascottes.',
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

    /**
     * @param  array<string, string|int|bool>  $charData
     */
    private function getCharacterJsonLd(array $charData, string $url): string
    {
        return (string) json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            'name' => ($charData['name']).' - WowPlanet',
            'url' => $url,
            'mainEntity' => [
                '@type' => 'Person',
                'name' => (string) $charData['name'],
                'description' => sprintf(
                    '%s %s niveau %s sur %s',
                    (string) ($charData['race'] ?? ''),
                    (string) ($charData['class'] ?? ''),
                    (string) ($charData['level'] ?? ''),
                    (string) ($charData['realm'] ?? ''),
                ),
                'image' => ((string) ($charData['avatarUrl'] ?? '')) ?: null,
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
