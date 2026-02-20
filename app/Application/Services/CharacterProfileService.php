<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\CharacterProfileDTO;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\ExpansionTierMatcher;
use App\Models\WowAchievement;
use App\Models\WowDecor;
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

        $decorResponse = [];
        try {
            $decorResponse = $this->blizzardApiClient->get(
                sprintf('profile/wow/character/%s/%s/collections/decor', $realm, $name),
            );
        } catch (\Exception) {
            // Character may not have housing unlocked — gracefully ignore
        }

        /** @var list<array{id: int}> $questsList */
        $questsList = $questsResponse['quests'] ?? [];
        $completedQuestIds = array_column($questsList, 'id');

        /** @var list<array{id: int}> $achievementsList */
        $achievementsList = $achievementsResponse['achievements'] ?? [];
        $completedAchievementIds = array_column($achievementsList, 'id');
        /** @var int|null $rawPoints */
        $rawPoints = $achievementsResponse['total_points'] ?? null;
        $achievementPoints = is_int($rawPoints) ? $rawPoints : 0;

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

        /** @var list<array{decor: array{id: int}}> $decorList */
        $decorList = $decorResponse['decor_collected'] ?? [];
        $characterDecorIds = array_map(
            fn(array $d): int => $d['decor']['id'],
            $decorList,
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
        $decor = $this->processDecorCollection(WowDecor::all(), $characterDecorIds);
        $professions = $this->aggregateProfessionProgress($professionsResponse, $characterFaction);

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
        /** @var array{name?: string} $guildData */
        $guildData = $summary['guild'] ?? [];

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
            achievementPoints: $achievementPoints,
            guild: (string) ($guildData['name'] ?? ''),
            mounts: $mounts,
            pets: $pets,
            professions: $professions,
            decorCount: count($characterDecorIds),
            decor: $decor,
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
                'icon_url' => $allItem->icon_url ?? null,
            ];
        }

        return $result;
    }

    /**
     * @param Collection<int, WowDecor> $allItems
     * @param list<int> $characterIds
     * @return list<array{id: int, name: string, is_completed: bool, item_id: int|null, icon_url: string|null}>
     */
    private function processDecorCollection(Collection $allItems, array $characterIds): array
    {
        $result = [];
        foreach ($allItems as $allItem) {
            $result[] = [
                'id' => $allItem->id,
                'name' => $allItem->name_fr,
                'is_completed' => in_array($allItem->id, $characterIds),
                'item_id' => $allItem->item_id,
                'icon_url' => $allItem->icon_url,
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
    private function aggregateProfessionProgress(array $professionsResponse, string $characterFaction): array
    {
        $results = [];

        /** @var list<array{profession: array{id: int, name: string}, skill_points?: int, max_skill_points?: int, tiers?: list<array{tier: array{id: int, name: string}, skill_points?: int, max_skill_points?: int, known_recipes?: list<array{id: int, name: string}>}>}> $primaries */
        $primaries = $professionsResponse['primaries'] ?? [];
        /** @var list<array{profession: array{id: int, name: string}, skill_points?: int, max_skill_points?: int, tiers?: list<array{tier: array{id: int, name: string}, skill_points?: int, max_skill_points?: int, known_recipes?: list<array{id: int, name: string}>}>}> $secondaries */
        $secondaries = $professionsResponse['secondaries'] ?? [];

        $allCharProfessions = array_merge($primaries, $secondaries);

        foreach ($allCharProfessions as $allCharProfession) {
            $professionId = $allCharProfession['profession']['id'];

            // Collect all known recipe IDs and skill points per expansion
            $knownRecipeIds = [];
            /** @var array<int, array{skill_points: int, max_skill_points: int}> $skillPointsByExpansion */
            $skillPointsByExpansion = [];

            foreach ($allCharProfession['tiers'] ?? [] as $tier) {
                foreach ($tier['known_recipes'] ?? [] as $recipe) {
                    $knownRecipeIds[] = $recipe['id'];
                }

                // Map tier name to expansion ID for skill points
                $tierName = $tier['tier']['name'];
                $tierExpansionId = ExpansionTierMatcher::match($tierName) ?? 0;
                $skillPointsByExpansion[$tierExpansionId] = [
                    'skill_points' => $tier['skill_points'] ?? 0,
                    'max_skill_points' => $tier['max_skill_points'] ?? 0,
                ];
            }

            // Get all recipes for this profession from DB, filtered by faction
            /** @var Collection<int, WowRecipe> $allRecipes */
            $allRecipes = WowRecipe::query()
                ->where('profession_id', $professionId)
                ->where('is_active', true)
                ->where(fn (\Illuminate\Contracts\Database\Query\Builder $builder) => $builder->whereNull('faction')->orWhere('faction', $characterFaction))
                ->get();

            $profession = WowProfession::find($professionId);

            // DB-stored max skill levels per expansion (from game data API import)
            /** @var array<int, int> $dbMaxSkillLevels */
            $dbMaxSkillLevels = $profession->max_skill_levels ?? [];

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

                    // Deduplicate ranked recipes (e.g., BfA gathering: 3 ranks with same name)
                    $items = $this->deduplicateRankedRecipes($items);
                    $completed = count(array_filter($items, fn (array $item) => $item['is_completed']));

                    $categoryProgress[] = [
                        'name' => $catName,
                        'total' => count($items),
                        'completed' => $completed,
                        'items' => $items,
                    ];
                }

                $total = array_sum(array_column($categoryProgress, 'total'));
                $completedCount = array_sum(array_column($categoryProgress, 'completed'));

                // Determine if the character has learned this tier
                $hasTier = array_key_exists($exp, $skillPointsByExpansion);
                // Tier exists in game data for this profession?
                $tierExistsInGame = array_key_exists($exp, $dbMaxSkillLevels);

                $expansionProgress[$exp] = [
                    'total' => $total,
                    'completed' => $completedCount,
                    'categories' => $categoryProgress,
                    'has_tier' => $hasTier,
                    'tier_exists' => $tierExistsInGame,
                    'skill_points' => $skillPointsByExpansion[$exp]['skill_points'] ?? 0,
                    'max_skill_points' => $hasTier
                        ? ($skillPointsByExpansion[$exp]['max_skill_points'] ?? 0)
                        : ($dbMaxSkillLevels[$exp] ?? 0),
                ];
            }

            // Top-level skill points (used by Archaeology which has no tiers)
            $globalSkillPoints = $allCharProfession['skill_points'] ?? 0;
            $globalMaxSkillPoints = $allCharProfession['max_skill_points'] ?? 0;

            $results[] = [
                'profession_id' => $professionId,
                'profession_name' => $profession !== null
                    ? $profession->name_fr
                    : (self::PROFESSION_NAMES_FR[$professionId] ?? $allCharProfession['profession']['name']),
                'type' => $profession !== null
                    ? $profession->type
                    : (in_array($professionId, [185, 356, 794], true) ? 'secondary' : 'primary'),
                'is_archaeology' => $professionId === 794,
                'global_skill_points' => $globalSkillPoints,
                'global_max_skill_points' => $globalMaxSkillPoints,
                'expansions' => $expansionProgress,
            ];
        }

        return $results;
    }

    /**
     * Deduplicate ranked recipes (e.g., BfA gathering professions have Rank 1/2/3 with the same name).
     * For each group of same-named recipes, keep only the highest rank learned,
     * or the highest rank if none are learned.
     *
     * @param list<array{id: int, name: string, is_completed: bool, wowhead_spell_id: int|null}> $items
     * @return list<array{id: int, name: string, is_completed: bool, wowhead_spell_id: int|null}>
     */
    private function deduplicateRankedRecipes(array $items): array
    {
        /** @var array<string, list<array{id: int, name: string, is_completed: bool, wowhead_spell_id: int|null}>> $groups */
        $groups = [];
        foreach ($items as $item) {
            $groups[$item['name']][] = $item;
        }

        $result = [];
        foreach ($groups as $group) {
            if (count($group) === 1) {
                $result[] = $group[0];

                continue;
            }

            // Sort by ID descending (highest rank = highest ID)
            usort($group, fn (array $a, array $b): int => $b['id'] <=> $a['id']);

            // Pick the highest completed rank, or the highest rank if none completed
            $picked = $group[0]; // default: highest rank (not completed)
            foreach ($group as $entry) {
                if ($entry['is_completed']) {
                    $picked = $entry;

                    break; // highest completed rank (already sorted desc)
                }
            }

            $result[] = $picked;
        }

        return $result;
    }
}
