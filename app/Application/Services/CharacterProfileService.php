<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\CharacterProfileDTO;
use App\Application\Services\Progress\AchievementProgressAggregator;
use App\Application\Services\Progress\CollectionProgressAggregator;
use App\Application\Services\Progress\EquipmentAggregator;
use App\Application\Services\Progress\ProfessionProgressAggregator;
use App\Application\Services\Progress\QuestProgressAggregator;
use App\Application\Services\Progress\ReputationProgressAggregator;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Log;

class CharacterProfileService
{
    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
        private readonly QuestProgressAggregator $questProgressAggregator,
        private readonly AchievementProgressAggregator $achievementProgressAggregator,
        private readonly CollectionProgressAggregator $collectionProgressAggregator,
        private readonly ProfessionProgressAggregator $professionProgressAggregator,
        private readonly ReputationProgressAggregator $reputationProgressAggregator,
        private readonly EquipmentAggregator $equipmentAggregator,
        private readonly UserCharacterService $userCharacterService,
    ) {}

    public function getProfile(string $realm, string $name): CharacterProfileDTO
    {
        $realm = mb_strtolower($realm);
        $name = mb_strtolower($name);

        $apiData = $this->fetchCharacterData($realm, $name);

        /** @var array<string, mixed> $summary */
        $summary = $apiData['summary'];
        /** @var list<int> $completedQuestIds */
        $completedQuestIds = $apiData['completedQuestIds'];
        /** @var list<int> $completedAchievementIds */
        $completedAchievementIds = $apiData['completedAchievementIds'];
        /** @var list<int> $characterMountIds */
        $characterMountIds = $apiData['characterMountIds'];
        /** @var list<int> $characterPetIds */
        $characterPetIds = $apiData['characterPetIds'];
        /** @var list<int> $characterDecorIds */
        $characterDecorIds = $apiData['characterDecorIds'];
        /** @var list<int> $characterAppearanceIds */
        $characterAppearanceIds = $apiData['characterAppearanceIds'];
        /** @var array<string, mixed> $professionsResponse */
        $professionsResponse = $apiData['professionsResponse'];
        /** @var array<string, mixed> $reputationsResponse */
        $reputationsResponse = $apiData['reputationsResponse'];

        $characterFaction = $this->extractFaction($summary);

        $questProgress = $this->questProgressAggregator->aggregate($completedQuestIds, $characterFaction);
        $achievementProgress = $this->achievementProgressAggregator->aggregate($completedAchievementIds);
        $reputationProgress = $this->reputationProgressAggregator->aggregate($reputationsResponse, $characterFaction);

        $collections = $this->mergeCollections($questProgress, $achievementProgress, $reputationProgress);

        $mounts = $this->collectionProgressAggregator->aggregateMounts($characterMountIds);
        $pets = $this->collectionProgressAggregator->aggregatePets($characterPetIds);
        $decor = $this->collectionProgressAggregator->aggregateDecor($characterDecorIds);
        $appearances = $this->collectionProgressAggregator->aggregateAppearances($characterAppearanceIds);
        $professions = $this->professionProgressAggregator->aggregate($professionsResponse, $characterFaction);
        /** @var array<string, mixed> $equipmentResponseData */
        $equipmentResponseData = is_array($apiData['equipmentResponse'] ?? null) ? $apiData['equipmentResponse'] : [];
        /** @var array<int, string> $equipmentIconMap */
        $equipmentIconMap = $apiData['equipmentIconMap'] ?? [];
        $equipment = $this->equipmentAggregator->aggregate($equipmentResponseData, $equipmentIconMap);

        return $this->buildDto($apiData, $collections, $mounts, $pets, $decor, $professions, $equipment, $appearances);
    }

    private const MAX_ASYNC_RETRIES = 2;

    private const RETRY_DELAY_S = 3;

    /**
     * @return array<string, mixed>
     */
    private function fetchCharacterData(string $realm, string $name): array
    {
        $base = sprintf('profile/wow/character/%s/%s', $realm, $name);

        $summary = $this->blizzardApiClient->get($base);

        $endpoints = [
            'media' => ['endpoint' => $base.'/character-media', 'query' => []],
            'quests' => ['endpoint' => $base.'/quests/completed', 'query' => []],
            'achievements' => ['endpoint' => $base.'/achievements', 'query' => []],
            'mounts' => ['endpoint' => $base.'/collections/mounts', 'query' => []],
            'pets' => ['endpoint' => $base.'/collections/pets', 'query' => []],
            'professions' => ['endpoint' => $base.'/professions', 'query' => []],
            'reputations' => ['endpoint' => $base.'/reputations', 'query' => []],
            'decor' => ['endpoint' => $base.'/collections/decor', 'query' => []],
            'transmogs' => ['endpoint' => $base.'/collections/transmogs', 'query' => []],
            'mythicKeystone' => ['endpoint' => $base.'/mythic-keystone-profile', 'query' => []],
            'equipment' => ['endpoint' => $base.'/equipment', 'query' => []],
        ];

        $responses = $this->fetchAsync($endpoints);

        /** @var array<string, mixed> $media */
        $media = $responses['media'] ?? [];
        /** @var array<string, mixed> $questsResponse */
        $questsResponse = $responses['quests'] ?? [];
        /** @var array<string, mixed> $achievementsResponse */
        $achievementsResponse = $responses['achievements'] ?? [];
        /** @var array<string, mixed> $mountsResponse */
        $mountsResponse = $responses['mounts'] ?? [];
        /** @var array<string, mixed> $petsResponse */
        $petsResponse = $responses['pets'] ?? [];
        /** @var array<string, mixed> $professionsResponse */
        $professionsResponse = $responses['professions'] ?? [];
        /** @var array<string, mixed> $reputationsResponse */
        $reputationsResponse = $responses['reputations'] ?? [];
        /** @var array<string, mixed> $decorResponse */
        $decorResponse = $responses['decor'] ?? [];
        /** @var array<string, mixed> $transmogsResponse */
        $transmogsResponse = $responses['transmogs'] ?? [];

        /** @var list<array{id: int}> $questsList */
        $questsList = $questsResponse['quests'] ?? [];
        unset($questsResponse);
        /** @var list<array{id: int, completed_timestamp?: int}> $achievementsList */
        $achievementsList = $achievementsResponse['achievements'] ?? [];
        $achievementPoints = is_int($achievementsResponse['total_points'] ?? null)
            ? $achievementsResponse['total_points'] : 0;
        unset($achievementsResponse);
        /** @var list<array{mount: array{id: int}}> $mountsList */
        $mountsList = $mountsResponse['mounts'] ?? [];
        unset($mountsResponse);
        /** @var list<array{species: array{id: int}}> $petsList */
        $petsList = $petsResponse['pets'] ?? [];
        unset($petsResponse);
        /** @var list<array{decor: array{id: int}}> $decorList */
        $decorList = $decorResponse['decor_collected'] ?? [];
        unset($decorResponse);
        /** @var list<array<string, mixed>> $transmogSlots */
        $transmogSlots = $transmogsResponse['slots'] ?? [];
        unset($transmogsResponse);

        /** @var array<string, mixed> $mythicKeystoneProfile */
        $mythicKeystoneProfile = $responses['mythicKeystone'] ?? [];
        /** @var array<string, mixed> $equipmentResponse */
        $equipmentResponse = $responses['equipment'] ?? [];
        unset($responses);

        $mythicKeystoneSeasonData = $this->fetchCurrentMythicSeason($base);

        $equipmentIconMap = $this->fetchEquipmentIcons($equipmentResponse);

        return [
            'summary' => $summary,
            'media' => $media,
            'completedQuestIds' => array_column($questsList, 'id'),
            'completedAchievementIds' => array_column(
                array_filter($achievementsList, fn (array $a): bool => isset($a['completed_timestamp'])),
                'id',
            ),
            'characterMountIds' => array_map(fn (array $m): int => $m['mount']['id'], $mountsList),
            'characterPetIds' => array_map(fn (array $p): int => $p['species']['id'], $petsList),
            'characterDecorIds' => array_map(fn (array $d): int => $d['decor']['id'], $decorList),
            'characterAppearanceIds' => $this->extractAppearanceIds($transmogSlots),
            'achievementPoints' => $achievementPoints,
            'professionsResponse' => $professionsResponse,
            'reputationsResponse' => $reputationsResponse,
            'mythicKeystoneProfile' => $mythicKeystoneProfile,
            'mythicKeystoneSeasonData' => $mythicKeystoneSeasonData,
            'equipmentResponse' => $equipmentResponse,
            'equipmentIconMap' => $equipmentIconMap,
        ];
    }

    /**
     * @param  array<string, array{endpoint: string, query: array<string, mixed>}>  $endpoints
     * @return array<string, array<string, mixed>>
     */
    private function fetchAsync(array $endpoints): array
    {
        $results = [];
        /** @var array<string, array{endpoint: string, query: array<string, mixed>}> $pending */
        $pending = $endpoints;

        for ($attempt = 0; $attempt <= self::MAX_ASYNC_RETRIES; $attempt++) {
            if ($attempt > 0 && $pending !== []) {
                Log::info(sprintf('Profile API: retrying %d failed requests (attempt %d/%d)', count($pending), $attempt, self::MAX_ASYNC_RETRIES));
                \Illuminate\Support\Sleep::usleep(self::RETRY_DELAY_S * 1_000_000);
            }

            $promises = [];
            foreach ($pending as $key => $config) {
                $promises[$key] = $this->blizzardApiClient->getAsync($config['endpoint'], $config['query']);
            }

            /** @var array<string, array{state: string, value?: \Psr\Http\Message\ResponseInterface, reason?: \Throwable}> $settled */
            $settled = Utils::settle($promises)->wait();

            $failed = [];
            foreach ($settled as $key => $result) {
                if ($result['state'] === 'fulfilled' && isset($result['value'])) {
                    /** @var array<string, mixed> $decoded */
                    $decoded = json_decode($result['value']->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                    $result['value']->getBody()->close();
                    unset($settled[$key]);
                    $results[$key] = $decoded;

                    continue;
                }

                $reason = $result['reason'] ?? null;

                // 404 = not found (e.g. decor not unlocked), treat as empty
                if ($reason instanceof RequestException
                    && $reason->getResponse() instanceof \Psr\Http\Message\ResponseInterface
                    && $reason->getResponse()->getStatusCode() === 404) {
                    $results[$key] = [];

                    continue;
                }

                // Retryable: timeout, 500, 504, rate limit
                if ($this->isRetryableError($reason)) {
                    $failed[$key] = $pending[$key];
                    Log::debug(sprintf('Profile API async error [%s]: %s', $pending[$key]['endpoint'], $reason instanceof \Throwable ? $reason->getMessage() : 'unknown'));

                    continue;
                }

                // Non-retryable error, treat as empty
                Log::warning(sprintf('Profile API non-retryable error [%s]: %s', $pending[$key]['endpoint'], $reason instanceof \Throwable ? $reason->getMessage() : 'unknown'));
                $results[$key] = [];
            }

            $pending = $failed;

            if ($pending === []) {
                break;
            }
        }

        // Any remaining failures after retries → empty
        foreach (array_keys($pending) as $key) {
            Log::warning(sprintf('Profile API gave up on [%s] after %d retries', $pending[$key]['endpoint'], self::MAX_ASYNC_RETRIES));
            $results[$key] = [];
        }

        return $results;
    }

    private function isRetryableError(mixed $reason): bool
    {
        if (! $reason instanceof \Throwable) {
            return false;
        }

        $message = $reason->getMessage();

        return str_contains($message, '429')
            || str_contains($message, '500')
            || str_contains($message, '504')
            || str_contains($message, 'timed out')
            || str_contains($message, 'cURL error');
    }

    /**
     * Aplatit les apparences transmog débloquées : slots[].appearances[].id
     * (ids item-appearance = PK du référentiel wow_appearances).
     *
     * @param  list<array<string, mixed>>  $transmogSlots
     * @return list<int>
     */
    private function extractAppearanceIds(array $transmogSlots): array
    {
        $ids = [];
        foreach ($transmogSlots as $transmogSlot) {
            $appearances = is_array($transmogSlot['appearances'] ?? null) ? $transmogSlot['appearances'] : [];
            foreach ($appearances as $appearance) {
                if (is_array($appearance) && is_numeric($appearance['id'] ?? null)) {
                    $ids[] = (int) $appearance['id'];
                }
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function extractFaction(array $summary): string
    {
        /** @var array{name?: string} $factionData */
        $factionData = $summary['faction'] ?? [];

        return (string) ($factionData['name'] ?? '');
    }

    /**
     * @param  array<int, array{total: int, completed: int, zones: list<array<string, mixed>>}>  $questProgress
     * @param  array<int, array{total: int, completed: int, categories: list<array<string, mixed>>}>  $achievementProgress
     * @param  array<int, array{total: int, completed: int, factions: list<array<string, mixed>>}>  $reputationProgress
     * @return array<int, array<string, mixed>>
     */
    private function mergeCollections(array $questProgress, array $achievementProgress, array $reputationProgress): array
    {
        $collections = [];

        for ($i = 0; $i <= 11; $i++) {
            $collections[$i] = [
                'quests' => $questProgress[$i] ?? ['total' => 0, 'completed' => 0, 'zones' => []],
                'achievements' => $achievementProgress[$i] ?? ['total' => 0, 'completed' => 0, 'categories' => []],
                'reputations' => $reputationProgress[$i] ?? ['total' => 0, 'completed' => 0, 'factions' => []],
            ];
        }

        return $collections;
    }

    /**
     * @param  array<int, array<string, mixed>>  $collections
     */
    private function countExalted(array $collections): int
    {
        $count = 0;
        foreach ($collections as $collection) {
            /** @var array{completed: int, total: int} $reputations */
            $reputations = $collection['reputations'] ?? ['completed' => 0, 'total' => 0];
            $count += $reputations['completed'];
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $equipmentResponse
     * @return array<int, string> Map of itemId => iconUrl
     */
    private function fetchEquipmentIcons(array $equipmentResponse): array
    {
        /** @var list<array<string, mixed>> $equippedItems */
        $equippedItems = $equipmentResponse['equipped_items'] ?? [];

        if ($equippedItems === []) {
            return [];
        }

        $endpoints = [];
        foreach ($equippedItems as $equippedItem) {
            /** @var array{id?: int} $itemData */
            $itemData = $equippedItem['item'] ?? [];
            $itemId = (int) ($itemData['id'] ?? 0);

            if ($itemId > 0) {
                $endpoints['item_'.$itemId] = [
                    'endpoint' => sprintf('data/wow/media/item/%d', $itemId),
                    'query' => ['namespace' => 'static-'.$this->blizzardApiClient->getRegion()],
                ];
            }
        }

        if ($endpoints === []) {
            return [];
        }

        $responses = $this->fetchAsync($endpoints);

        $iconMap = [];
        foreach ($responses as $key => $response) {
            $itemId = (int) str_replace('item_', '', $key);
            /** @var list<array{key?: string, value?: string}> $assets */
            $assets = $response['assets'] ?? [];

            foreach ($assets as $asset) {
                if (($asset['key'] ?? '') === 'icon' && isset($asset['value'])) {
                    $iconMap[$itemId] = $asset['value'];
                    break;
                }
            }
        }

        return $iconMap;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchCurrentMythicSeason(string $base): array
    {
        $currentSeasonId = $this->blizzardApiClient->getCurrentMythicSeasonId();

        if ($currentSeasonId === 0) {
            return [];
        }

        try {
            /** @var array<string, mixed> $seasonData */
            $seasonData = $this->blizzardApiClient->get(
                $base.'/mythic-keystone-profile/season/'.$currentSeasonId,
            );

            return $seasonData;
        } catch (\Throwable $throwable) {
            Log::debug('M+ season fetch failed: '.$throwable->getMessage());

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $seasonData
     * @return array<string, mixed>|null
     */
    private function buildMythicKeystoneData(array $profile, array $seasonData): ?array
    {
        if ($profile === [] || $seasonData === []) {
            return null;
        }

        /** @var array{rating?: float, color?: array{r: int, g: int, b: int, a: float}} $mythicRating */
        $mythicRating = $seasonData['mythic_rating'] ?? [];

        /** @var list<array<string, mixed>> $bestRuns */
        $bestRuns = $seasonData['best_runs'] ?? [];

        $runs = [];
        foreach ($bestRuns as $bestRun) {
            /** @var array{name?: string, id?: int} $dungeon */
            $dungeon = $bestRun['dungeon'] ?? [];
            /** @var array{rating?: float, color?: array{r: int, g: int, b: int, a: float}} $runRating */
            $runRating = $bestRun['mythic_rating'] ?? [];
            /** @var array{rating?: float, color?: array{r: int, g: int, b: int, a: float}} $mapRating */
            $mapRating = $bestRun['map_rating'] ?? [];
            /** @var list<array{character?: array{name?: string, realm?: array{name?: string}}, specialization?: array{name?: string}, equipped_item_level?: int}> $members */
            $members = $bestRun['members'] ?? [];

            $keystoneLevel = is_int($bestRun['keystone_level'] ?? null) ? $bestRun['keystone_level'] : 0;
            $duration = is_int($bestRun['duration'] ?? null) ? $bestRun['duration'] : 0;
            $completedTimestamp = is_int($bestRun['completed_timestamp'] ?? null) ? $bestRun['completed_timestamp'] : 0;

            $runs[] = [
                'dungeon_name' => (string) ($dungeon['name'] ?? ''),
                'dungeon_id' => (int) ($dungeon['id'] ?? 0),
                'level' => $keystoneLevel,
                'duration_ms' => $duration,
                'completed_at' => $completedTimestamp,
                'is_timed' => (bool) ($bestRun['is_completed_within_time'] ?? false),
                'score' => round((float) ($runRating['rating'] ?? 0), 1),
                'score_color' => $runRating['color'] ?? null,
                'map_score' => round((float) ($mapRating['rating'] ?? 0), 1),
                'map_score_color' => $mapRating['color'] ?? null,
                'members' => array_map(fn (array $m): array => [
                    'name' => $m['character']['name'] ?? '',
                    'realm' => $m['character']['realm']['name'] ?? '',
                    'spec' => $m['specialization']['name'] ?? '',
                    'ilvl' => $m['equipped_item_level'] ?? 0,
                ], $members),
            ];
        }

        usort($runs, fn (array $a, array $b): int => $b['map_score'] <=> $a['map_score']);

        /** @var array{id?: int} $season */
        $season = $seasonData['season'] ?? [];

        return [
            'rating' => isset($mythicRating['rating']) ? round($mythicRating['rating'], 1) : null,
            'rating_color' => $mythicRating['color'] ?? null,
            'season_id' => (int) ($season['id'] ?? 0),
            'best_runs' => $runs,
        ];
    }

    /**
     * @param  array<string, mixed>  $apiData
     * @param  array<int, array<string, mixed>>  $collections
     * @param  list<array<string, mixed>>  $mounts
     * @param  list<array<string, mixed>>  $pets
     * @param  list<array<string, mixed>>  $decor
     * @param  list<array<string, mixed>>  $professions
     * @param  list<array{slot: string, slot_name: string, item_id: int, name: string, item_level: int, quality: string, icon_url: string|null}>  $equipment
     * @param  list<array{slot: string, category: string|null, total: int, completed: int}>  $appearances
     */
    private function buildDto(array $apiData, array $collections, array $mounts, array $pets, array $decor, array $professions, array $equipment = [], array $appearances = []): CharacterProfileDTO
    {
        /** @var array<string, mixed> $summary */
        $summary = $apiData['summary'];
        /** @var array{id?: int, name?: string} $charClass */
        $charClass = $summary['character_class'] ?? [];
        /** @var array<string, mixed> $mediaData */
        $mediaData = $apiData['media'] ?? [];
        /** @var list<array{value: string}> $mediaAssets */
        $mediaAssets = $mediaData['assets'] ?? [];
        $classId = (int) ($charClass['id'] ?? 0);
        $classIcons = $this->userCharacterService->getClassIcons();
        $classIconUrl = $classIcons[$classId] ?? '';

        /** @var list<int> $mountIds */
        $mountIds = $apiData['characterMountIds'];
        /** @var list<int> $petIds */
        $petIds = $apiData['characterPetIds'];
        /** @var list<int> $decorIds */
        $decorIds = $apiData['characterDecorIds'];

        /** @var array{name?: string} $realmData */
        $realmData = $summary['realm'] ?? [];
        /** @var array{name?: string} $raceData */
        $raceData = $summary['race'] ?? [];
        /** @var array{name?: string} $guildData */
        $guildData = $summary['guild'] ?? [];

        /** @var array<string, mixed> $mythicProfile */
        $mythicProfile = $apiData['mythicKeystoneProfile'] ?? [];
        /** @var array<string, mixed> $mythicSeason */
        $mythicSeason = $apiData['mythicKeystoneSeasonData'] ?? [];

        return new CharacterProfileDTO(
            name: is_string($summary['name'] ?? null) ? $summary['name'] : '',
            realm: (string) ($realmData['name'] ?? ''),
            race: (string) ($raceData['name'] ?? ''),
            class: (string) ($charClass['name'] ?? ''),
            classId: (int) ($charClass['id'] ?? 0),
            level: is_int($summary['level'] ?? null) ? $summary['level'] : 0,
            ilvl: is_int($summary['equipped_item_level'] ?? null) ? $summary['equipped_item_level'] : 0,
            faction: $this->extractFaction($summary),
            avatarUrl: (string) ($mediaAssets[1]['value'] ?? $mediaAssets[0]['value'] ?? ''),
            classIconUrl: $classIconUrl,
            collections: $collections,
            mountsCount: count($mountIds),
            petsCount: count($petIds),
            achievementPoints: is_int($apiData['achievementPoints'] ?? null) ? $apiData['achievementPoints'] : 0,
            guild: (string) ($guildData['name'] ?? ''),
            mounts: $mounts,
            pets: $pets,
            professions: $professions,
            decorCount: count($decorIds),
            decor: $decor,
            exaltedCount: $this->countExalted($collections),
            mythicKeystone: $this->buildMythicKeystoneData($mythicProfile, $mythicSeason),
            completedQuestIds: is_array($apiData['completedQuestIds'] ?? null) ? array_values(array_filter($apiData['completedQuestIds'], is_int(...))) : [],
            completedAchievementIds: is_array($apiData['completedAchievementIds'] ?? null) ? array_values(array_filter($apiData['completedAchievementIds'], is_int(...))) : [],
            equipment: $equipment,
            appearances: $appearances,
            appearancesCount: array_sum(array_column($appearances, 'completed')),
        );
    }

    /**
     * Lightweight fetch for cross-character data: only quests, achievements, reputations, professions.
     *
     * @return array{questIds: list<int>, achievementIds: list<int>, reputations: array<string, mixed>, professions: array<string, mixed>}
     */
    public function fetchCrossCharacterRawData(string $realm, string $name): array
    {
        $realm = mb_strtolower($realm);
        $name = mb_strtolower($name);

        $base = sprintf('profile/wow/character/%s/%s', $realm, $name);

        $endpoints = [
            'quests' => ['endpoint' => $base.'/quests/completed', 'query' => []],
            'achievements' => ['endpoint' => $base.'/achievements', 'query' => []],
            'reputations' => ['endpoint' => $base.'/reputations', 'query' => []],
            'professions' => ['endpoint' => $base.'/professions', 'query' => []],
        ];

        $responses = $this->fetchAsync($endpoints);

        /** @var array<string, mixed> $questsResponse */
        $questsResponse = $responses['quests'] ?? [];
        /** @var array<string, mixed> $achievementsResponse */
        $achievementsResponse = $responses['achievements'] ?? [];

        /** @var list<array{id: int}> $questsList */
        $questsList = $questsResponse['quests'] ?? [];
        /** @var list<array{id: int, completed_timestamp?: int}> $achievementsList */
        $achievementsList = $achievementsResponse['achievements'] ?? [];

        return [
            'questIds' => array_column($questsList, 'id'),
            'achievementIds' => array_column(
                array_filter($achievementsList, fn (array $a): bool => isset($a['completed_timestamp'])),
                'id',
            ),
            'reputations' => $responses['reputations'] ?? [],
            'professions' => $responses['professions'] ?? [],
        ];
    }
}
