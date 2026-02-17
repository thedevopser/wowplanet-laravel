<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\CharacterProfileDTO;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Models\WowQuest;
use App\Models\WowAchievement;
use App\Models\WowMount;
use App\Models\WowPet;
use Illuminate\Support\Collection;

class CharacterProfileService
{
    public function __construct(
        private BlizzardApiClient $apiClient
    ) {}

    public function getProfile(string $realm, string $name): CharacterProfileDTO
    {
        $realm = strtolower($realm);
        $name = strtolower($name);

        // 1. API Calls for Character Data
        $summary = $this->apiClient->get("profile/wow/character/{$realm}/{$name}");
        $media = $this->apiClient->get("profile/wow/character/{$realm}/{$name}/character-media");

        $classId = $summary['character_class']['id'] ?? 0;
        $classMedia = $this->apiClient->get("data/wow/media/playable-class/{$classId}", [
            'namespace' => 'static-' . config('services.blizzard.region', 'eu'),
        ]);

        $questsResponse = $this->apiClient->get("profile/wow/character/{$realm}/{$name}/quests/completed");
        $achievementsResponse = $this->apiClient->get("profile/wow/character/{$realm}/{$name}/achievements");
        $mountsResponse = $this->apiClient->get("profile/wow/character/{$realm}/{$name}/collections/mounts");
        $petsResponse = $this->apiClient->get("profile/wow/character/{$realm}/{$name}/collections/pets");

        // 2. Extract Completed IDs
        $completedQuestIds = array_column($questsResponse['quests'] ?? [], 'id');
        $completedAchievementIds = array_column($achievementsResponse['achievements'] ?? [], 'id');
        
        $characterMountIds = array_column(array_map(fn($m) => $m['mount'], $mountsResponse['mounts'] ?? []), 'id');
        $characterPetIds = array_column(array_map(fn($p) => $p['species'], $petsResponse['pets'] ?? []), 'id');

        // 3. Compare with Local Database (The "Source of Truth")
        $collections = $this->aggregateProgress($completedQuestIds, $completedAchievementIds);
        
        $mounts = $this->processCollection(WowMount::all(), $characterMountIds);
        $pets = $this->processCollection(WowPet::all(), $characterPetIds);

        $classIconUrl = '';
        foreach ($classMedia['assets'] ?? [] as $asset) {
            if ($asset['key'] === 'icon') {
                $classIconUrl = $asset['value'];
                break;
            }
        }

        return new CharacterProfileDTO(
            name: $summary['name'],
            realm: $summary['realm']['name'],
            race: $summary['race']['name'],
            class: $summary['character_class']['name'],
            classId: $classId,
            level: $summary['level'],
            ilvl: $summary['equipped_item_level'],
            faction: $summary['faction']['name'],
            avatarUrl: $media['assets'][1]['value'] ?? $media['assets'][0]['value'] ?? '',
            classIconUrl: $classIconUrl,
            collections: $collections,
            mountsCount: count($characterMountIds),
            petsCount: count($characterPetIds),
            mounts: $mounts,
            pets: $pets,
        );
    }

    private function aggregateProgress(array $completedQuests, array $completedAchievements): array
    {
        $results = [];

        // Fetch ALL quests and achievements from local DB, grouped by expansion
        // This is fast because of the indexes
        $allQuests = WowQuest::where('is_active', true)->get()->groupBy('expansion_id');
        $allAchievements = WowAchievement::where('is_active', true)->get()->groupBy('expansion_id');

        // Iterate over expansions 0-11
        for ($i = 0; $i <= 11; $i++) {
            // Quests
            $expansionQuests = $allQuests->get($i, new Collection());
            $questsByZone = $expansionQuests->groupBy('zone_name');
            
            $zoneProgress = [];
            foreach ($questsByZone as $zoneName => $quests) {
                if (empty($zoneName)) continue;

                $items = $quests->map(function ($quest) use ($completedQuests) {
                    return [
                        'id' => $quest->id,
                        'name' => $quest->name_fr,
                        'is_completed' => in_array($quest->id, $completedQuests),
                    ];
                })->values()->toArray();

                $completedCount = count(array_filter($items, fn($i) => $i['is_completed']));

                $zoneProgress[] = [
                    'name' => $zoneName,
                    'total' => count($items),
                    'completed' => $completedCount,
                    'items' => $items,
                ];
            }

            // Achievements
            $expansionAchievements = $allAchievements->get($i, new Collection());
            $achievementsByCategory = $expansionAchievements->groupBy('category_name');

            $categoryProgress = [];
            foreach ($achievementsByCategory as $categoryName => $achievements) {
                if (empty($categoryName)) continue;

                $items = $achievements->map(function ($ach) use ($completedAchievements) {
                    return [
                        'id' => $ach->id,
                        'name' => $ach->name_fr,
                        'is_completed' => in_array($ach->id, $completedAchievements),
                    ];
                })->values()->toArray();

                $completedCount = count(array_filter($items, fn($i) => $i['is_completed']));

                $categoryProgress[] = [
                    'name' => $categoryName,
                    'total' => count($items),
                    'completed' => $completedCount,
                    'items' => $items,
                ];
            }

            $totalQuests = $expansionQuests->count();
            $completedQuestsCount = $expansionQuests->whereIn('id', $completedQuests)->count();

            $totalAchievements = $expansionAchievements->count();
            $completedAchievementsCount = $expansionAchievements->whereIn('id', $completedAchievements)->count();

            $results[$i] = [
                'quests' => [
                    'total' => $totalQuests,
                    'completed' => $completedQuestsCount,
                    'zones' => $zoneProgress,
                ],
                'achievements' => [
                    'total' => $totalAchievements,
                    'completed' => $completedAchievementsCount,
                    'categories' => $categoryProgress,
                ],
            ];
        }

        return $results;
    }

    private function processCollection(Collection $allItems, array $characterIds): array
    {
        return $allItems->map(function ($item) use ($characterIds) {
            return [
                'id' => $item->id,
                'name' => $item->name_fr,
                'is_completed' => in_array($item->id, $characterIds),
                // Add source if available
                'source' => $item->source ?? null, 
            ];
        })->values()->toArray();
    }
}