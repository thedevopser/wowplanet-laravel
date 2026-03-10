<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\CharacterProfileDTO;
use App\Application\DTOs\CrossCharacterProgress;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Jobs\ComputeCrossCharacterJob;
use App\Models\CrossCharacterData;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

class CrossCharacterService
{
    private const CACHE_TTL_HOURS = 24;

    private const MAX_RETRIES = 3;

    private const RETRY_BASE_DELAY_S = 5;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
        private readonly UserCharacterService $userCharacterService,
    ) {}

    /**
     * Dispatch cross-character computation as a background job.
     * Returns cached data if fresh, otherwise queues the computation.
     *
     * @return array{status: string, data?: array<string, mixed>|null, characterCount?: int, jobId?: string}
     */
    public function compute(): array
    {
        if (! $this->userCharacterService->isAuthenticated()) {
            return ['status' => 'unauthenticated'];
        }

        $bnetUserId = $this->getBnetUserId();
        if ($bnetUserId === '') {
            return ['status' => 'unauthenticated'];
        }

        $stored = $this->getStoredData();
        if ($stored !== null) {
            return ['status' => 'ready', 'data' => $stored['data'], 'characterCount' => $stored['character_count']];
        }

        $characters = $this->userCharacterService->getUserCharacters();
        if ($characters === []) {
            return ['status' => 'ready', 'data' => null];
        }

        $jobId = Str::uuid()->toString();
        $token = $this->blizzardApiClient->getAccessToken();

        Cache::put('cross_character:'.$jobId, ['status' => 'pending'], 3600);
        dispatch(new ComputeCrossCharacterJob($jobId, $bnetUserId, array_values($characters), $token));

        return ['status' => 'computing', 'jobId' => $jobId];
    }

    /**
     * @return array{status: string}
     */
    public function getJobStatus(string $jobId): array
    {
        /** @var array{status: string} $result */
        $result = Cache::get('cross_character:'.$jobId, ['status' => 'not_found']);

        return $result;
    }

    /**
     * Read stored cross-character data from DB (instant).
     *
     * @return array{data: array<string, mixed>, character_count: int}|null
     */
    public function getStoredData(): ?array
    {
        $bnetUserId = $this->getBnetUserId();
        if ($bnetUserId === '') {
            return null;
        }

        /** @var CrossCharacterData|null $record */
        $record = CrossCharacterData::query()->find($bnetUserId);

        if ($record === null || $record->fetched_at === null) {
            return null;
        }

        if ($record->fetched_at->diffInHours(now()) >= self::CACHE_TTL_HOURS) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $record->data;

        return ['data' => $data, 'character_count' => $record->character_count];
    }

    /**
     * Merge data from a single character profile into existing cross-character data (piggyback).
     */
    public function mergeCurrentCharacter(CharacterProfileDTO $characterProfileDTO): void
    {
        $bnetUserId = $this->getBnetUserId();
        if ($bnetUserId === '') {
            return;
        }

        /** @var CrossCharacterData|null $record */
        $record = CrossCharacterData::query()->find($bnetUserId);

        $crossCharacterProgress = new CrossCharacterProgress;

        if ($record !== null && $record->data !== []) {
            $this->hydrateProgressFromStored($crossCharacterProgress, $record->data);
        }

        $crossCharacterProgress->mergeFromProfile($characterProfileDTO->name, $characterProfileDTO);

        $result = $crossCharacterProgress->buildResult();

        CrossCharacterData::query()->updateOrCreate(['bnet_user_id' => $bnetUserId], [
            'data' => $result,
            'character_count' => $record !== null ? $record->character_count : 1,
            'fetched_at' => $record?->fetched_at,
        ]);
    }

    /**
     * Fetch and merge characters one by one to minimize memory.
     *
     * @param  list<array<string, mixed>>  $characters
     */
    public function fetchAndMergeCharacters(array $characters, CrossCharacterProgress $crossCharacterProgress, ?string $accessToken = null): void
    {
        $token = $accessToken ?? $this->blizzardApiClient->getAccessToken();
        /** @var string $region */
        $region = config('services.blizzard.region', 'eu');
        $baseUrl = sprintf('https://%s.api.blizzard.com', $region);
        $namespace = 'profile-'.$region;

        foreach ($characters as $character) {
            $realm = mb_strtolower(is_string($character['realmSlug'] ?? null) ? $character['realmSlug'] : '');
            $charName = is_string($character['name'] ?? null) ? $character['name'] : '';
            $nameLower = mb_strtolower($charName);
            if ($realm === '') {
                continue;
            }

            if ($charName === '') {
                continue;
            }

            $base = sprintf('%s/profile/wow/character/%s/%s', $baseUrl, $realm, $nameLower);
            $rawData = $this->fetchOneCharacter($base, $namespace, $token);
            $crossCharacterProgress->mergeCharacter($charName, $rawData);
            unset($rawData);
        }
    }

    /**
     * Fetch 4 endpoints for a single character one at a time to minimize memory.
     *
     * @return array{questIds: list<int>, achievementIds: list<int>, reputations: array<string, mixed>, professions: array<string, mixed>}
     */
    private function fetchOneCharacter(string $baseUrl, string $namespace, string $token): array
    {
        $questIds = $this->fetchAndExtract(
            $baseUrl.'/quests/completed',
            $namespace,
            $token,
            static function (Response $response): mixed {
                /** @var list<array{id: int}> $quests */
                $quests = $response->json('quests') ?? [];

                return array_column($quests, 'id');
            },
        );

        $achievementIds = $this->fetchAndExtract(
            $baseUrl.'/achievements',
            $namespace,
            $token,
            static function (Response $response): mixed {
                /** @var list<array{id: int, completed_timestamp?: int}> $achievements */
                $achievements = $response->json('achievements') ?? [];

                return array_column(
                    array_filter($achievements, static fn (array $a): bool => isset($a['completed_timestamp'])),
                    'id',
                );
            },
        );

        /** @var array<string, mixed> $reputations */
        $reputations = $this->fetchAndExtract(
            $baseUrl.'/reputations',
            $namespace,
            $token,
            static fn (Response $response): mixed => $response->json() ?? [],
        );

        /** @var array<string, mixed> $professions */
        $professions = $this->fetchAndExtract(
            $baseUrl.'/professions',
            $namespace,
            $token,
            static fn (Response $response): mixed => $response->json() ?? [],
        );

        /** @var list<int> $questIdsList */
        $questIdsList = is_array($questIds) ? array_values($questIds) : [];
        /** @var list<int> $achievementIdsList */
        $achievementIdsList = is_array($achievementIds) ? array_values($achievementIds) : [];

        return [
            'questIds' => $questIdsList,
            'achievementIds' => $achievementIdsList,
            'reputations' => $reputations,
            'professions' => $professions,
        ];
    }

    /**
     * Fetch a single endpoint with retry, extract only needed data via callback.
     */
    private function fetchAndExtract(string $url, string $namespace, string $token, \Closure $extractor): mixed
    {
        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            if ($attempt > 0) {
                Sleep::sleep(self::RETRY_BASE_DELAY_S * (2 ** ($attempt - 1)));
            }

            try {
                $response = Http::withToken($token)
                    ->withHeaders(['Battlenet-Namespace' => $namespace])
                    ->timeout(15)
                    ->get($url, ['locale' => 'fr_FR']);

                if ($response->successful()) {
                    $result = $extractor($response);
                    unset($response);

                    return $result;
                }

                if ($response->status() === 404) {
                    return [];
                }

                Log::debug(sprintf('Cross-character fetch error: HTTP %d for %s', $response->status(), $url));
            } catch (\Throwable $e) {
                Log::debug(sprintf('Cross-character fetch error: %s for %s', $e->getMessage(), $url));
            }
        }

        return [];
    }

    /**
     * Hydrate a CrossCharacterProgress from stored DB data.
     *
     * @param  array<string, mixed>  $storedData
     */
    private function hydrateProgressFromStored(CrossCharacterProgress $crossCharacterProgress, array $storedData): void
    {
        /** @var array<int|string, string> $questOwners */
        $questOwners = $storedData['questOwners'] ?? [];
        foreach ($questOwners as $id => $charName) {
            $crossCharacterProgress->completedQuestIds[(int) $id] = $charName;
        }

        // Backward compat: old format had completedQuestIds as list<int> without owners
        if ($questOwners === []) {
            /** @var list<int> $questIds */
            $questIds = $storedData['completedQuestIds'] ?? [];
            foreach ($questIds as $questId) {
                $crossCharacterProgress->completedQuestIds[$questId] ??= '';
            }
        }

        /** @var array<int|string, string> $achievementOwners */
        $achievementOwners = $storedData['achievementOwners'] ?? [];
        foreach ($achievementOwners as $id => $charName) {
            $crossCharacterProgress->completedAchievementIds[(int) $id] = $charName;
        }

        if ($achievementOwners === []) {
            /** @var list<int> $achievementIds */
            $achievementIds = $storedData['completedAchievementIds'] ?? [];
            foreach ($achievementIds as $achievementId) {
                $crossCharacterProgress->completedAchievementIds[$achievementId] ??= '';
            }
        }

        /** @var list<int> $recipeIds */
        $recipeIds = $storedData['completedRecipeIds'] ?? [];
        foreach ($recipeIds as $id) {
            $crossCharacterProgress->completedRecipeIds[$id] = true;
        }

        /** @var array<int|string, array{character_name: string, tier: int, raw: int, renown_level: int, standing_name: string, completed: bool}> $factionStandings */
        $factionStandings = $storedData['bestFactionStandings'] ?? [];
        foreach ($factionStandings as $factionId => $standing) {
            $crossCharacterProgress->bestFactionStandings[(int) $factionId] = $standing;
        }

        /** @var array<int|string, string> $recipeOwners */
        $recipeOwners = $storedData['recipeOwners'] ?? [];
        foreach ($recipeOwners as $recipeId => $charName) {
            $crossCharacterProgress->recipeOwners[(int) $recipeId] = $charName;
        }

        /** @var array<int|string, array<int|string, array{character_name: string, skill_points: int, max_skill_points: int}>> $skillPointOwners */
        $skillPointOwners = $storedData['skillPointOwners'] ?? [];
        foreach ($skillPointOwners as $profId => $expansions) {
            foreach ($expansions as $expId => $data) {
                $crossCharacterProgress->skillPointOwners[(int) $profId][(int) $expId] = $data;
            }
        }
    }

    private function getBnetUserId(): string
    {
        /** @var string $userId */
        $userId = Session::get('bnet_user_id', '');

        return $userId;
    }
}
