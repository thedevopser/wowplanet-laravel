<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\CharacterProfileDTO;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Models\WowAchievement;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use Illuminate\Support\Collection;

class CharacterProfileService
{
    /**
     * Fallback French names for professions (Profile API returns English regardless of locale).
     *
     * @var array<int, string>
     */
    private const PROFESSION_NAMES_FR = [
        164 => 'Forge',
        165 => 'Travail du cuir',
        171 => 'Alchimie',
        182 => 'Herboristerie',
        185 => 'Cuisine',
        186 => 'Minage',
        197 => 'Couture',
        202 => 'Ingénierie',
        333 => 'Enchantement',
        356 => 'Pêche',
        393 => 'Dépeçage',
        755 => 'Joaillerie',
        773 => 'Calligraphie',
        794 => 'Archéologie',
    ];

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
    ) {
    }

    public function getProfile(string $realm, string $name): CharacterProfileDTO
    {
        $realm = strtolower($realm);
        $name = strtolower($name);

        $summary = $this->blizzardApiClient->get(
            sprintf('profile/wow/character/%s/%s', $realm, $name),
        );
        $media = $this->blizzardApiClient->get(
            sprintf('profile/wow/character/%s/%s/character-media', $realm, $name),
        );

        /** @var array{id?: int, name?: string} $charClass */
        $charClass = $summary['character_class'] ?? [];
        $classId = (int) ($charClass['id'] ?? 0);

        /** @var string $region */
        $region = config('services.blizzard.region', 'eu');
        $classMedia = $this->blizzardApiClient->get(
            'data/wow/media/playable-class/' . $classId,
            ['namespace' => 'static-' . $region],
        );

        $questsResponse = $this->blizzardApiClient->get(
            sprintf('profile/wow/character/%s/%s/quests/completed', $realm, $name),
        );
        $achievementsResponse = $this->blizzardApiClient->get(
            sprintf('profile/wow/character/%s/%s/achievements', $realm, $name),
        );
        $mountsResponse = $this->blizzardApiClient->get(
            sprintf('profile/wow/character/%s/%s/collections/mounts', $realm, $name),
        );
        $petsResponse = $this->blizzardApiClient->get(
            sprintf('profile/wow/character/%s/%s/collections/pets', $realm, $name),
        );
        $professionsResponse = $this->blizzardApiClient->get(
            sprintf('profile/wow/character/%s/%s/professions', $realm, $name),
        );

        /** @var list<array{id: int}> $questsList */
        $questsList = $questsResponse['quests'] ?? [];
        $completedQuestIds = array_column($questsList, 'id');

        /** @var list<array{id: int}> $achievementsList */
        $achievementsList = $achievementsResponse['achievements'] ?? [];
        $completedAchievementIds = array_column($achievementsList, 'id');

        /** @var list<array{mount: array{id: int}}> $mountsList */
        $mountsList = $mountsResponse['mounts'] ?? [];
        $characterMountIds = array_map(
            fn(array $m): int => $m['mount']['id'],
            $mountsList,
        );

        /** @var list<array{species: array{id: int}}> $petsList */
        $petsList = $petsResponse['pets'] ?? [];
        $characterPetIds = array_map(
            fn(array $p): int => $p['species']['id'],
            $petsList,
        );

        /** @var array{name?: string} $factionData */
        $factionData = $summary['faction'] ?? [];
        $characterFaction = (string) ($factionData['name'] ?? '');

        $collections = $this->aggregateProgress(
            $completedQuestIds,
            $completedAchievementIds,
            $characterFaction,
        );

        $mounts = $this->processCollection(WowMount::all(), $characterMountIds);
        $pets = $this->processCollection(WowPet::all(), $characterPetIds);
        $professions = $this->aggregateProfessionProgress($professionsResponse);

        $classIconUrl = '';
        /** @var list<array{key: string, value: string}> $classAssets */
        $classAssets = $classMedia['assets'] ?? [];
        foreach ($classAssets as $classAsset) {
            if ($classAsset['key'] === 'icon') {
                $classIconUrl = $classAsset['value'];
                break;
            }
        }

        /** @var list<array{value: string}> $mediaAssets */
        $mediaAssets = $media['assets'] ?? [];

        /** @var array{name?: string} $realmData */
        $realmData = $summary['realm'] ?? [];
        /** @var array{name?: string} $raceData */
        $raceData = $summary['race'] ?? [];

        $summaryName = is_string($summary['name'] ?? null) ? $summary['name'] : '';
        $summaryLevel = is_int($summary['level'] ?? null) ? $summary['level'] : 0;
        $summaryIlvl = is_int($summary['equipped_item_level'] ?? null)
            ? $summary['equipped_item_level'] : 0;

        return new CharacterProfileDTO(
            name: $summaryName,
            realm: (string) ($realmData['name'] ?? ''),
            race: (string) ($raceData['name'] ?? ''),
            class: (string) ($charClass['name'] ?? ''),
            classId: $classId,
            level: $summaryLevel,
            ilvl: $summaryIlvl,
            faction: $characterFaction,
            avatarUrl: (string) ($mediaAssets[1]['value'] ?? $mediaAssets[0]['value'] ?? ''),
            classIconUrl: $classIconUrl,
            collections: $collections,
            mountsCount: count($characterMountIds),
            petsCount: count($characterPetIds),
            mounts: $mounts,
            pets: $pets,
            professions: $professions,
        );
    }

    /**
     * @param list<int> $completedQuests
     * @param list<int> $completedAchievements
     * @return array<int, array<string, mixed>>
     */
    private function aggregateProgress(
        array $completedQuests,
        array $completedAchievements,
        string $faction,
    ): array {
        $results = [];

        /** @var Collection<int, WowQuest> $allQuestsRaw */
        $allQuestsRaw = WowQuest::query()
            ->where('is_active', true)
            ->where(fn (\Illuminate\Contracts\Database\Query\Builder $builder) => $builder->whereNull('faction')->orWhere('faction', $faction))
            ->get();
        $allQuests = $allQuestsRaw->groupBy('expansion_id');

        /** @var Collection<int, WowAchievement> $allAchievementsRaw */
        $allAchievementsRaw = WowAchievement::query()->where('is_active', true)->get();
        $allAchievements = $allAchievementsRaw->groupBy('expansion_id');

        for ($expansionIndex = 0; $expansionIndex <= 11; $expansionIndex++) {
            /** @var Collection<int, WowQuest> $expansionQuests */
            $expansionQuests = $allQuests->get($expansionIndex, new Collection());
            /** @var Collection<string, Collection<int, WowQuest>> $questsByZone */
            $questsByZone = $expansionQuests->groupBy('zone_name');

            $zoneProgress = [];
            foreach ($questsByZone as $zoneName => $zoneQuests) {
                if (empty($zoneName)) {
                    continue;
                }

                $items = [];
                $completedCount = 0;
                foreach ($zoneQuests as $zoneQuest) {
                    $isCompleted = in_array($zoneQuest->id, $completedQuests);
                    $items[] = [
                        'id' => $zoneQuest->id,
                        'name' => $zoneQuest->name_fr,
                        'is_completed' => $isCompleted,
                    ];
                    if ($isCompleted) {
                        $completedCount++;
                    }
                }

                $zoneProgress[] = [
                    'name' => $zoneName,
                    'total' => count($items),
                    'completed' => $completedCount,
                    'items' => $items,
                ];
            }

            /** @var Collection<int, WowAchievement> $expansionAchievements */
            $expansionAchievements = $allAchievements->get($expansionIndex, new Collection());
            /** @var Collection<string, Collection<int, WowAchievement>> $achievementsByCategory */
            $achievementsByCategory = $expansionAchievements->groupBy('category_name');

            $categoryProgress = [];
            foreach ($achievementsByCategory as $categoryName => $categoryAchievements) {
                if (empty($categoryName)) {
                    continue;
                }

                $items = [];
                $completedCount = 0;
                foreach ($categoryAchievements as $categoryAchievement) {
                    $isCompleted = in_array($categoryAchievement->id, $completedAchievements);
                    $items[] = [
                        'id' => $categoryAchievement->id,
                        'name' => $categoryAchievement->name_fr,
                        'is_completed' => $isCompleted,
                    ];
                    if ($isCompleted) {
                        $completedCount++;
                    }
                }

                $categoryProgress[] = [
                    'name' => $categoryName,
                    'total' => count($items),
                    'completed' => $completedCount,
                    'items' => $items,
                ];
            }

            $totalQuests = array_sum(array_column($zoneProgress, 'total'));
            $completedQuestsCount = array_sum(array_column($zoneProgress, 'completed'));

            $totalAchievements = $expansionAchievements->count();
            $completedAchievementsCount = $expansionAchievements
                ->whereIn('id', $completedAchievements)->count();

            $results[$expansionIndex] = [
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

    /**
     * @param Collection<int, WowMount|WowPet> $allItems
     * @param list<int> $characterIds
     * @return list<array{id: int, name: string, is_completed: bool, source: string|null, wowhead_id: int|null}>
     */
    private function processCollection(Collection $allItems, array $characterIds): array
    {
        $result = [];
        foreach ($allItems as $allItem) {
            $wowheadId = $allItem instanceof WowMount
                ? $allItem->source_spell_id
                : $allItem->creature_id;

            $result[] = [
                'id' => $allItem->id,
                'name' => $allItem->name_fr,
                'is_completed' => in_array($allItem->id, $characterIds),
                'source' => $allItem->source ?? null,
                'wowhead_id' => $wowheadId,
            ];
        }

        return $result;
    }

    /**
     * Aggregate profession progress: compare character's known recipes with all recipes in DB.
     *
     * @param array<string, mixed> $professionsResponse
     * @return list<array<string, mixed>>
     */
    private function aggregateProfessionProgress(array $professionsResponse): array
    {
        $results = [];

        /** @var list<array{profession: array{id: int, name: string}, tiers?: list<array{tier: array{id: int, name: string}, known_recipes?: list<array{id: int, name: string}>}>}> $primaries */
        $primaries = $professionsResponse['primaries'] ?? [];
        /** @var list<array{profession: array{id: int, name: string}, tiers?: list<array{tier: array{id: int, name: string}, known_recipes?: list<array{id: int, name: string}>}>}> $secondaries */
        $secondaries = $professionsResponse['secondaries'] ?? [];

        $allCharProfessions = array_merge($primaries, $secondaries);

        foreach ($allCharProfessions as $allCharProfession) {
            $professionId = $allCharProfession['profession']['id'];

            // Collect all known recipe IDs for this profession
            $knownRecipeIds = [];
            foreach ($allCharProfession['tiers'] ?? [] as $tier) {
                foreach ($tier['known_recipes'] ?? [] as $recipe) {
                    $knownRecipeIds[] = $recipe['id'];
                }
            }

            // Get all recipes for this profession from DB
            /** @var Collection<int, WowRecipe> $allRecipes */
            $allRecipes = WowRecipe::query()
                ->where('profession_id', $professionId)
                ->where('is_active', true)
                ->get();

            $recipesByExpansion = $allRecipes->groupBy('expansion_id');
            $expansionProgress = [];

            for ($exp = 0; $exp <= 11; $exp++) {
                /** @var Collection<int, WowRecipe> $expansionRecipes */
                $expansionRecipes = $recipesByExpansion->get($exp, new Collection());
                /** @var Collection<string, Collection<int, WowRecipe>> $recipesByCategory */
                $recipesByCategory = $expansionRecipes->groupBy('category_name');

                $categoryProgress = [];
                foreach ($recipesByCategory as $catName => $catRecipes) {
                    if (empty($catName)) {
                        continue;
                    }

                    $items = [];
                    $completed = 0;
                    foreach ($catRecipes as $catRecipe) {
                        $isKnown = in_array($catRecipe->id, $knownRecipeIds);
                        $items[] = [
                            'id' => $catRecipe->id,
                            'name' => $catRecipe->name_fr,
                            'is_completed' => $isKnown,
                            'wowhead_spell_id' => $catRecipe->wowhead_spell_id,
                        ];
                        if ($isKnown) {
                            $completed++;
                        }
                    }

                    $categoryProgress[] = [
                        'name' => $catName,
                        'total' => count($items),
                        'completed' => $completed,
                        'items' => $items,
                    ];
                }

                $total = $expansionRecipes->count();
                $completedCount = $expansionRecipes->whereIn('id', $knownRecipeIds)->count();

                $expansionProgress[$exp] = [
                    'total' => $total,
                    'completed' => $completedCount,
                    'categories' => $categoryProgress,
                ];
            }

            $profession = WowProfession::find($professionId);

            $results[] = [
                'profession_id' => $professionId,
                'profession_name' => $profession !== null
                    ? $profession->name_fr
                    : (self::PROFESSION_NAMES_FR[$professionId] ?? $allCharProfession['profession']['name']),
                'type' => $profession !== null
                    ? $profession->type
                    : (in_array($professionId, [185, 356, 794], true) ? 'secondary' : 'primary'),
                'expansions' => $expansionProgress,
            ];
        }

        return $results;
    }
}
