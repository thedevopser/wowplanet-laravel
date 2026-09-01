<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Services\PvpBracketClassifier;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Classements PvP officiels, servis en direct depuis l'API Blizzard.
 *
 * Aucune table, aucun classement reconstitué à partir des personnages déjà vus :
 * Blizzard publie lui-même le classement complet de la saison par bracket. Seules
 * les colonnes affichées sont mises en cache, le reste de la réponse (plusieurs
 * milliers d'entrées très bavardes) est jeté à la lecture.
 */
class PvpLeaderboardService
{
    public const DEFAULT_BRACKET = '3v3';

    private const PER_PAGE = 50;

    private const INDEX_TTL_S = 86400;

    private const LEADERBOARD_TTL_S = 3600;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
        private readonly PvpBracketClassifier $pvpBracketClassifier,
        private readonly PlayableNameService $playableNameService,
    ) {}

    /**
     * Brackets classés de la saison, regroupés par mode.
     *
     * @return list<array{key: string, label: string, brackets: list<array{slug: string, label: string, short: string}>}>
     */
    public function availableBrackets(): array
    {
        $slugs = $this->bracketSlugs();

        if ($slugs === []) {
            return [];
        }

        $groups = [];
        foreach (PvpBracketClassifier::GROUPS as $key => $label) {
            $brackets = [];
            foreach ($slugs as $slug) {
                if ($this->pvpBracketClassifier->groupFor($slug) === $key) {
                    $spec = $this->frenchSpecFor($slug);

                    $brackets[] = [
                        'slug' => $slug,
                        'label' => $this->pvpBracketClassifier->labelFor($slug, $spec),
                        'short' => $this->pvpBracketClassifier->shortLabelFor($slug, $spec),
                    ];
                }
            }

            if ($brackets === []) {
                continue;
            }

            // « Toutes spés » d'abord : c'est le classement par défaut du mode.
            usort($brackets, fn (array $a, array $b): int => [$this->isOverall($a['slug']) ? 0 : 1, $a['slug']]
                <=> [$this->isOverall($b['slug']) ? 0 : 1, $b['slug']]);
            $groups[] = ['key' => $key, 'label' => $label, 'brackets' => $brackets];
        }

        return $groups;
    }

    /**
     * @return array{bracket: string, label: string, seasonId: int, entries: list<array<string, mixed>>,
     *     total: int, currentPage: int, lastPage: int, unavailable: bool}
     */
    public function leaderboard(string $bracket, int $page = 1, ?string $search = null): array
    {
        $bracket = $this->resolveBracket($bracket);
        $seasonId = $this->currentSeasonId();

        $entries = $seasonId > 0 ? $this->entriesFor($seasonId, $bracket) : null;

        if ($entries === null) {
            return $this->emptyResult($bracket, $seasonId, true);
        }

        $entries = $this->filter($entries, $search);
        $total = count($entries);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $currentPage = min(max($page, 1), $lastPage);

        return [
            'bracket' => $bracket,
            'label' => $this->pvpBracketClassifier->labelFor($bracket, $this->frenchSpecFor($bracket)),
            'seasonId' => $seasonId,
            'entries' => array_slice($entries, ($currentPage - 1) * self::PER_PAGE, self::PER_PAGE),
            'total' => $total,
            'currentPage' => $currentPage,
            'lastPage' => $lastPage,
            'unavailable' => false,
        ];
    }

    /**
     * L'index des classements ne nomme ses brackets qu'en anglais : on retraduit
     * « deathknight-blood » via les index de classes et spécialisations.
     */
    private function frenchSpecFor(string $slug): ?string
    {
        $specSlugs = $this->pvpBracketClassifier->specSlugsFor($slug);

        if ($specSlugs === null) {
            return null;
        }

        return $this->playableNameService->labelFor($specSlugs[0], $specSlugs[1]);
    }

    private function isOverall(string $slug): bool
    {
        return str_ends_with($slug, '-overall');
    }

    /**
     * Un slug inconnu de l'index retombe sur le bracket par défaut. Quand l'index
     * est indisponible, on se contente de rejeter ce qui ne ressemble pas à un slug.
     */
    private function resolveBracket(string $bracket): string
    {
        $slugs = $this->bracketSlugs();

        if ($slugs !== []) {
            return in_array($bracket, $slugs, true) ? $bracket : self::DEFAULT_BRACKET;
        }

        return preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $bracket) === 1 ? $bracket : self::DEFAULT_BRACKET;
    }

    /**
     * @return list<string>
     */
    private function bracketSlugs(): array
    {
        $seasonId = $this->currentSeasonId();

        if ($seasonId === 0) {
            return [];
        }

        /** @var list<string> $slugs */
        $slugs = Cache::remember(
            'pvp_leaderboard_index:'.$seasonId,
            self::INDEX_TTL_S,
            function () use ($seasonId): array {
                try {
                    $response = $this->blizzardApiClient->get(
                        sprintf('data/wow/pvp-season/%d/pvp-leaderboard/index', $seasonId),
                        ['namespace' => 'dynamic-'.$this->blizzardApiClient->getRegion()],
                    );
                } catch (\Throwable $throwable) {
                    Log::debug('PvP leaderboard index fetch failed: '.$throwable->getMessage());

                    return [];
                }

                /** @var list<array{name?: string}> $leaderboards */
                $leaderboards = is_array($response['leaderboards'] ?? null) ? $response['leaderboards'] : [];

                $slugs = [];
                foreach ($leaderboards as $leaderboard) {
                    $name = $leaderboard['name'] ?? null;
                    if (is_string($name) && $name !== '') {
                        $slugs[] = $name;
                    }
                }

                return $slugs;
            },
        );

        return $slugs;
    }

    /**
     * @return list<array<string, mixed>>|null null quand le classement est indisponible
     */
    private function entriesFor(int $seasonId, string $bracket): ?array
    {
        /** @var list<array<string, mixed>>|null $entries */
        $entries = Cache::get(sprintf('pvp_leaderboard:%d:%s', $seasonId, $bracket));

        if ($entries !== null) {
            return $entries;
        }

        try {
            $response = $this->blizzardApiClient->get(
                sprintf('data/wow/pvp-season/%d/pvp-leaderboard/%s', $seasonId, $bracket),
                ['namespace' => 'dynamic-'.$this->blizzardApiClient->getRegion()],
            );
        } catch (\Throwable $throwable) {
            Log::warning('PvP leaderboard fetch failed', ['bracket' => $bracket, 'exception' => $throwable->getMessage()]);

            return null;
        }

        /** @var list<array<string, mixed>> $rawEntries */
        $rawEntries = is_array($response['entries'] ?? null) ? $response['entries'] : [];

        $entries = array_map($this->buildEntry(...), $rawEntries);

        Cache::put(sprintf('pvp_leaderboard:%d:%s', $seasonId, $bracket), $entries, self::LEADERBOARD_TTL_S);

        return $entries;
    }

    /**
     * N'extrait que les colonnes affichées : la réponse brute pèse plusieurs Mo.
     *
     * @param  array<string, mixed>  $entry
     * @return array{rank: int, name: string, realm: string, realm_slug: string, faction: string, rating: int, won: int, lost: int}
     */
    private function buildEntry(array $entry): array
    {
        /** @var array{name?: string, realm?: array{slug?: string}} $character */
        $character = is_array($entry['character'] ?? null) ? $entry['character'] : [];
        /** @var array{slug?: string} $realm */
        $realm = is_array($character['realm'] ?? null) ? $character['realm'] : [];
        /** @var array{type?: string} $faction */
        $faction = is_array($entry['faction'] ?? null) ? $entry['faction'] : [];
        /** @var array{won?: int, lost?: int} $statistics */
        $statistics = is_array($entry['season_match_statistics'] ?? null) ? $entry['season_match_statistics'] : [];

        $realmSlug = is_string($realm['slug'] ?? null) ? $realm['slug'] : '';

        return [
            'rank' => $this->intValue($entry, 'rank'),
            'name' => is_string($character['name'] ?? null) ? $character['name'] : '',
            'realm' => ucwords(str_replace('-', ' ', $realmSlug)),
            'realm_slug' => $realmSlug,
            'faction' => is_string($faction['type'] ?? null) ? $faction['type'] : '',
            'rating' => $this->intValue($entry, 'rating'),
            'won' => $this->intValue($statistics, 'won'),
            'lost' => $this->intValue($statistics, 'lost'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function intValue(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function filter(array $entries, ?string $search): array
    {
        $needle = mb_strtolower(trim((string) $search));

        if ($needle === '') {
            return $entries;
        }

        return array_values(array_filter($entries, function (array $entry) use ($needle): bool {
            $name = is_string($entry['name'] ?? null) ? $entry['name'] : '';
            $realm = is_string($entry['realm'] ?? null) ? $entry['realm'] : '';

            return str_contains(mb_strtolower($name), $needle) || str_contains(mb_strtolower($realm), $needle);
        }));
    }

    /**
     * @return array{bracket: string, label: string, seasonId: int, entries: list<array<string, mixed>>,
     *     total: int, currentPage: int, lastPage: int, unavailable: bool}
     */
    private function emptyResult(string $bracket, int $seasonId, bool $unavailable): array
    {
        return [
            'bracket' => $bracket,
            'label' => $this->pvpBracketClassifier->labelFor($bracket, $this->frenchSpecFor($bracket)),
            'seasonId' => $seasonId,
            'entries' => [],
            'total' => 0,
            'currentPage' => 1,
            'lastPage' => 1,
            'unavailable' => $unavailable,
        ];
    }

    private function currentSeasonId(): int
    {
        try {
            return $this->blizzardApiClient->getCurrentPvpSeasonId();
        } catch (\Throwable $throwable) {
            Log::debug('PvP season index fetch failed: '.$throwable->getMessage());

            return 0;
        }
    }
}
