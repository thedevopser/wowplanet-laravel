<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\CharacterProfileDTO;
use App\Application\Services\Progress\AchievementProgressAggregator;
use App\Application\Services\Progress\CollectionProgressAggregator;
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
    ) {}

    public function getProfile(string $realm, string $name): CharacterProfileDTO
    {
        $realm = strtolower($realm);
        $name = strtolower($name);

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
        $professions = $this->professionProgressAggregator->aggregate($professionsResponse, $characterFaction);

        return $this->buildDto($apiData, $collections, $mounts, $pets, $decor, $professions);
    }

    private const MAX_ASYNC_RETRIES = 2;

    private const RETRY_DELAY_S = 3;

    /**
     * @return array<string, mixed>
     */
    private function fetchCharacterData(string $realm, string $name): array
    {
        $base = sprintf('profile/wow/character/%s/%s', $realm, $name);

        // Step 1: Summary must be fetched first (we need classId for class media)
        $summary = $this->blizzardApiClient->get($base);

        /** @var array{id?: int, name?: string} $charClass */
        $charClass = $summary['character_class'] ?? [];
        $classId = (int) ($charClass['id'] ?? 0);

        /** @var string $region */
        $region = config('services.blizzard.region', 'eu');

        // Step 2: All other endpoints in parallel
        $endpoints = [
            'media' => ['endpoint' => $base.'/character-media', 'query' => []],
            'classMedia' => ['endpoint' => 'data/wow/media/playable-class/'.$classId, 'query' => ['namespace' => 'static-'.$region]],
            'quests' => ['endpoint' => $base.'/quests/completed', 'query' => []],
            'achievements' => ['endpoint' => $base.'/achievements', 'query' => []],
            'mounts' => ['endpoint' => $base.'/collections/mounts', 'query' => []],
            'pets' => ['endpoint' => $base.'/collections/pets', 'query' => []],
            'professions' => ['endpoint' => $base.'/professions', 'query' => []],
            'reputations' => ['endpoint' => $base.'/reputations', 'query' => []],
            'decor' => ['endpoint' => $base.'/collections/decor', 'query' => []],
        ];

        $responses = $this->fetchAsync($endpoints);

        /** @var array<string, mixed> $media */
        $media = $responses['media'] ?? [];
        /** @var array<string, mixed> $classMedia */
        $classMedia = $responses['classMedia'] ?? [];
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

        /** @var list<array{id: int}> $questsList */
        $questsList = $questsResponse['quests'] ?? [];
        /** @var list<array{id: int, completed_timestamp?: int}> $achievementsList */
        $achievementsList = $achievementsResponse['achievements'] ?? [];
        /** @var list<array{mount: array{id: int}}> $mountsList */
        $mountsList = $mountsResponse['mounts'] ?? [];
        /** @var list<array{species: array{id: int}}> $petsList */
        $petsList = $petsResponse['pets'] ?? [];
        /** @var list<array{decor: array{id: int}}> $decorList */
        $decorList = $decorResponse['decor_collected'] ?? [];

        return [
            'summary' => $summary,
            'media' => $media,
            'classMedia' => $classMedia,
            'completedQuestIds' => array_column($questsList, 'id'),
            'completedAchievementIds' => array_column(
                array_filter($achievementsList, fn (array $a): bool => isset($a['completed_timestamp'])),
                'id',
            ),
            'characterMountIds' => array_map(fn (array $m): int => $m['mount']['id'], $mountsList),
            'characterPetIds' => array_map(fn (array $p): int => $p['species']['id'], $petsList),
            'characterDecorIds' => array_map(fn (array $d): int => $d['decor']['id'], $decorList),
            'achievementPoints' => is_int($achievementsResponse['total_points'] ?? null)
                ? $achievementsResponse['total_points'] : 0,
            'professionsResponse' => $professionsResponse,
            'reputationsResponse' => $reputationsResponse,
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
     * @param  array<string, mixed>  $apiData
     * @param  array<int, array<string, mixed>>  $collections
     * @param  list<array<string, mixed>>  $mounts
     * @param  list<array<string, mixed>>  $pets
     * @param  list<array<string, mixed>>  $decor
     * @param  list<array<string, mixed>>  $professions
     */
    private function buildDto(array $apiData, array $collections, array $mounts, array $pets, array $decor, array $professions): CharacterProfileDTO
    {
        /** @var array<string, mixed> $summary */
        $summary = $apiData['summary'];
        /** @var array{id?: int, name?: string} $charClass */
        $charClass = $summary['character_class'] ?? [];
        /** @var array<string, mixed> $mediaData */
        $mediaData = $apiData['media'] ?? [];
        /** @var list<array{value: string}> $mediaAssets */
        $mediaAssets = $mediaData['assets'] ?? [];
        /** @var array<string, mixed> $classMediaData */
        $classMediaData = $apiData['classMedia'] ?? [];
        /** @var list<array{key: string, value: string}> $classAssets */
        $classAssets = $classMediaData['assets'] ?? [];

        $classIconUrl = '';
        foreach ($classAssets as $classAsset) {
            if ($classAsset['key'] === 'icon') {
                $classIconUrl = $classAsset['value'];

                break;
            }
        }

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
        );
    }
}
