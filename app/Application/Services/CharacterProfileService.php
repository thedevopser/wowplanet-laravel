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

    /**
     * @return array<string, mixed>
     */
    private function fetchCharacterData(string $realm, string $name): array
    {
        $base = sprintf('profile/wow/character/%s/%s', $realm, $name);

        $summary = $this->blizzardApiClient->get($base);
        $media = $this->blizzardApiClient->get($base.'/character-media');

        /** @var array{id?: int, name?: string} $charClass */
        $charClass = $summary['character_class'] ?? [];
        $classId = (int) ($charClass['id'] ?? 0);

        /** @var string $region */
        $region = config('services.blizzard.region', 'eu');
        $classMedia = $this->blizzardApiClient->get(
            'data/wow/media/playable-class/'.$classId,
            ['namespace' => 'static-'.$region],
        );

        $questsResponse = $this->blizzardApiClient->get($base.'/quests/completed');
        $achievementsResponse = $this->blizzardApiClient->get($base.'/achievements');
        $mountsResponse = $this->blizzardApiClient->get($base.'/collections/mounts');
        $petsResponse = $this->blizzardApiClient->get($base.'/collections/pets');
        $professionsResponse = $this->blizzardApiClient->get($base.'/professions');
        $reputationsResponse = $this->blizzardApiClient->get($base.'/reputations');

        $decorResponse = [];
        try {
            $decorResponse = $this->blizzardApiClient->get($base.'/collections/decor');
        } catch (\Exception) {
            // Character may not have housing unlocked
        }

        /** @var list<array{id: int}> $questsList */
        $questsList = $questsResponse['quests'] ?? [];
        /** @var list<array{id: int}> $achievementsList */
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
            'completedAchievementIds' => array_column($achievementsList, 'id'),
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
