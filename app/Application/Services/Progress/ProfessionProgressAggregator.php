<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Infrastructure\Blizzard\ExpansionTierMatcher;
use App\Models\WowProfession;
use App\Models\WowRecipe;
use Illuminate\Support\Collection;

class ProfessionProgressAggregator
{
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

    private const SECONDARY_PROFESSION_IDS = [185, 356, 794];

    /**
     * @param  array<string, mixed>  $professionsResponse
     * @return list<array<string, mixed>>
     */
    public function aggregate(array $professionsResponse, string $characterFaction): array
    {
        /** @var list<array<string, mixed>> $primaries */
        $primaries = $professionsResponse['primaries'] ?? [];
        /** @var list<array<string, mixed>> $secondaries */
        $secondaries = $professionsResponse['secondaries'] ?? [];

        $results = [];
        foreach (array_merge($primaries, $secondaries) as $charProfession) {
            $results[] = $this->aggregateSingleProfession($charProfession, $characterFaction);
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $charProfession
     * @return array<string, mixed>
     */
    private function aggregateSingleProfession(array $charProfession, string $characterFaction): array
    {
        /** @var array{id: int, name: string} $professionData */
        $professionData = $charProfession['profession'];
        $professionId = (int) $professionData['id'];
        $tierData = $this->extractTierData($charProfession);
        $allRecipes = $this->loadRecipesForProfession($professionId, $characterFaction);

        /** @var WowProfession|null $profession */
        $profession = WowProfession::query()->find($professionId);

        /** @var array<int, int> $dbMaxSkillLevels */
        $dbMaxSkillLevels = $profession !== null ? ($profession->max_skill_levels ?? []) : [];

        $expansionProgress = $this->buildExpansionProgress(
            $allRecipes,
            $tierData['knownRecipeIds'],
            $tierData['skillPointsByExpansion'],
            $dbMaxSkillLevels,
        );

        $professionName = $profession !== null
            ? (string) $profession->name_fr
            : (self::PROFESSION_NAMES_FR[$professionId] ?? (string) $professionData['name']);
        $professionType = $profession !== null
            ? (string) $profession->type
            : (in_array($professionId, self::SECONDARY_PROFESSION_IDS, true) ? 'secondary' : 'primary');

        return [
            'profession_id' => $professionId,
            'profession_name' => $professionName,
            'type' => $professionType,
            'is_archaeology' => $professionId === 794,
            'global_skill_points' => $charProfession['skill_points'] ?? 0,
            'global_max_skill_points' => $charProfession['max_skill_points'] ?? 0,
            'expansions' => $expansionProgress,
        ];
    }

    /**
     * @param  array<string, mixed>  $charProfession
     * @return array{knownRecipeIds: list<int>, skillPointsByExpansion: array<int, array{skill_points: int, max_skill_points: int}>}
     */
    private function extractTierData(array $charProfession): array
    {
        /** @var list<int> $knownRecipeIds */
        $knownRecipeIds = [];
        /** @var array<int, array{skill_points: int, max_skill_points: int}> $skillPointsByExpansion */
        $skillPointsByExpansion = [];

        /** @var list<array<string, mixed>> $tiers */
        $tiers = $charProfession['tiers'] ?? [];

        foreach ($tiers as $tier) {
            /** @var list<array{id: int}> $knownRecipes */
            $knownRecipes = $tier['known_recipes'] ?? [];
            foreach ($knownRecipes as $knownRecipe) {
                $knownRecipeIds[] = (int) $knownRecipe['id'];
            }

            /** @var array{name: string} $tierInfo */
            $tierInfo = $tier['tier'] ?? ['name' => ''];
            $tierExpansionId = ExpansionTierMatcher::match($tierInfo['name']) ?? 0;
            /** @var int $skillPoints */
            $skillPoints = $tier['skill_points'] ?? 0;
            /** @var int $maxSkillPoints */
            $maxSkillPoints = $tier['max_skill_points'] ?? 0;
            $skillPointsByExpansion[$tierExpansionId] = [
                'skill_points' => $skillPoints,
                'max_skill_points' => $maxSkillPoints,
            ];
        }

        return ['knownRecipeIds' => $knownRecipeIds, 'skillPointsByExpansion' => $skillPointsByExpansion];
    }

    /**
     * @return Collection<int, WowRecipe>
     */
    private function loadRecipesForProfession(int $professionId, string $characterFaction): Collection
    {
        return WowRecipe::query()
            ->where('profession_id', $professionId)
            ->where('is_active', true)
            ->where(fn (\Illuminate\Contracts\Database\Query\Builder $builder) => $builder->whereNull('faction')->orWhere('faction', $characterFaction))
            ->get();
    }

    /**
     * @param  Collection<int, WowRecipe>  $allRecipes
     * @param  list<int>  $knownRecipeIds
     * @param  array<int, array{skill_points: int, max_skill_points: int}>  $skillPointsByExpansion
     * @param  array<int, int>  $dbMaxSkillLevels
     * @return array<int, array<string, mixed>>
     */
    private function buildExpansionProgress(
        Collection $allRecipes,
        array $knownRecipeIds,
        array $skillPointsByExpansion,
        array $dbMaxSkillLevels,
    ): array {
        $recipesByExpansion = $allRecipes->groupBy('expansion_id');
        $expansionProgress = [];

        for ($exp = 0; $exp <= 11; $exp++) {
            /** @var Collection<int, WowRecipe> $expansionRecipes */
            $expansionRecipes = $recipesByExpansion->get($exp, new Collection);
            $categoryProgress = $this->buildCategoryProgress($expansionRecipes, $knownRecipeIds);

            $hasTier = array_key_exists($exp, $skillPointsByExpansion);
            $tierExistsInGame = array_key_exists($exp, $dbMaxSkillLevels);

            $expansionProgress[$exp] = [
                'total' => array_sum(array_column($categoryProgress, 'total')),
                'completed' => array_sum(array_column($categoryProgress, 'completed')),
                'categories' => $categoryProgress,
                'has_tier' => $hasTier,
                'tier_exists' => $tierExistsInGame,
                'skill_points' => $skillPointsByExpansion[$exp]['skill_points'] ?? 0,
                'max_skill_points' => $hasTier
                    ? ($skillPointsByExpansion[$exp]['max_skill_points'] ?? 0)
                    : ($dbMaxSkillLevels[$exp] ?? 0),
            ];
        }

        return $expansionProgress;
    }

    /**
     * @param  Collection<int, WowRecipe>  $expansionRecipes
     * @param  list<int>  $knownRecipeIds
     * @return list<array{name: string, total: int, completed: int, items: list<array<string, mixed>>}>
     */
    private function buildCategoryProgress(Collection $expansionRecipes, array $knownRecipeIds): array
    {
        $categoryProgress = [];
        /** @var Collection<string, Collection<int, WowRecipe>> $recipesByCategory */
        $recipesByCategory = $expansionRecipes->groupBy('category_name');

        foreach ($recipesByCategory as $catName => $catRecipes) {
            if (empty($catName)) {
                continue;
            }

            $items = $this->buildRecipeItems($catRecipes, $knownRecipeIds);
            $items = $this->deduplicateRankedRecipes($items);

            $categoryProgress[] = [
                'name' => $catName,
                'total' => count($items),
                'completed' => count(array_filter($items, fn (array $item) => $item['is_completed'])),
                'items' => $items,
            ];
        }

        return $categoryProgress;
    }

    /**
     * @param  Collection<int, WowRecipe>  $recipes
     * @param  list<int>  $knownRecipeIds
     * @return list<array{id: int, name: string, is_completed: bool, wowhead_spell_id: int|null}>
     */
    private function buildRecipeItems(Collection $recipes, array $knownRecipeIds): array
    {
        $items = [];
        foreach ($recipes as $recipe) {
            $items[] = [
                'id' => $recipe->id,
                'name' => $recipe->name_fr,
                'is_completed' => in_array($recipe->id, $knownRecipeIds),
                'wowhead_spell_id' => $recipe->wowhead_spell_id,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array{id: int, name: string, is_completed: bool, wowhead_spell_id: int|null}>  $items
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

            usort($group, fn (array $a, array $b): int => $b['id'] <=> $a['id']);

            $picked = $group[0];
            foreach ($group as $entry) {
                if ($entry['is_completed']) {
                    $picked = $entry;

                    break;
                }
            }

            $result[] = $picked;
        }

        return $result;
    }
}
