<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\ExpansionTierMatcher;
use App\Infrastructure\Blizzard\Support\Db2CsvLoader;
use App\Models\WowProfession;
use App\Models\WowRecipe;

class ProfessionImporter
{
    use ImportsFromBlizzardApi;

    private const SECONDARY_PROFESSION_IDS = [185, 356, 794];

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
    ) {}

    /**
     * @param  array<int, string>  $recipeFactionMap
     */
    public function import(array $recipeFactionMap = []): void
    {
        $this->info('Fetching profession index...');

        $index = $this->fetchWithRetry('data/wow/profession/index');
        if (! $index) {
            $this->info('ERROR: Could not fetch profession index.');

            return;
        }

        /** @var list<array{id: int, name: string}> $professions */
        $professions = $index['professions'] ?? [];
        $this->info('Found '.count($professions).' professions.');

        // skill_line_ability.csv: RaceMask(0), ..., ID(3), SkillLine(4), Spell(5)
        $recipeSpellMap = Db2CsvLoader::loadMap('skill_line_ability.csv', 3, 5);
        $this->info('  DB2 recipe spell map: '.count($recipeSpellMap).' entries.');
        $this->info('  DB2 recipe faction map: '.count($recipeFactionMap).' entries.');

        $totalRecipes = 0;

        foreach ($professions as $profession) {
            $totalRecipes += $this->importProfession($profession, $recipeSpellMap, $recipeFactionMap);
        }

        $this->info(sprintf('Profession import complete: %d professions, %d recipes.', count($professions), $totalRecipes));
    }

    /**
     * @param  array{id: int, name: string}  $profession
     * @param  array<int, int>  $recipeSpellMap
     * @param  array<int, string>  $recipeFactionMap
     */
    private function importProfession(array $profession, array $recipeSpellMap, array $recipeFactionMap): int
    {
        $professionId = $profession['id'];
        $professionName = $profession['name'];
        $type = in_array($professionId, self::SECONDARY_PROFESSION_IDS, true) ? 'secondary' : 'primary';

        $this->delayRequest();

        $detail = $this->fetchWithRetry('data/wow/profession/'.$professionId);
        if (! $detail) {
            return 0;
        }

        /** @var list<array{id: int, name: string}> $skillTiers */
        $skillTiers = $detail['skill_tiers'] ?? [];
        $this->info(sprintf('  %s (%s): %d skill tiers', $professionName, $type, count($skillTiers)));

        $totalRecipes = 0;
        /** @var array<int, int> $maxSkillLevels */
        $maxSkillLevels = [];

        foreach ($skillTiers as $skillTier) {
            $tierResult = $this->importSkillTierRecipes($professionId, $skillTier['id'], $skillTier['name'], $recipeSpellMap, $recipeFactionMap);
            $totalRecipes += $tierResult['count'];

            if ($tierResult['max_skill_level'] > 0) {
                $maxSkillLevels[$tierResult['expansion_id']] = $tierResult['max_skill_level'];
            }
        }

        WowProfession::query()->updateOrCreate(['id' => $professionId], [
            'name_fr' => $professionName,
            'type' => $type,
            'max_skill_levels' => $maxSkillLevels,
            'is_active' => true,
        ]);

        return $totalRecipes;
    }

    /**
     * @param  array<int, int>  $recipeSpellMap
     * @param  array<int, string>  $recipeFactionMap
     * @return array{count: int, expansion_id: int, max_skill_level: int}
     */
    private function importSkillTierRecipes(
        int $professionId,
        int $skillTierId,
        string $skillTierName,
        array $recipeSpellMap,
        array $recipeFactionMap = [],
    ): array {
        $this->delayRequest();

        $tierDetail = $this->fetchWithRetry(
            sprintf('data/wow/profession/%d/skill-tier/%d', $professionId, $skillTierId)
        );

        if (! $tierDetail) {
            return ['count' => 0, 'expansion_id' => 0, 'max_skill_level' => 0];
        }

        $expansionId = ExpansionTierMatcher::match($skillTierName) ?? 0;
        /** @var int $rawMaxSkill */
        $rawMaxSkill = $tierDetail['maximum_skill_level'] ?? 0;
        $maxSkillLevel = (int) $rawMaxSkill;
        $count = 0;

        /** @var list<array{name: string, recipes?: list<array{id: int, name: string}>}> $categories */
        $categories = $tierDetail['categories'] ?? [];

        foreach ($categories as $category) {
            $count += $this->importCategoryRecipes($category, $professionId, $expansionId, $recipeSpellMap, $recipeFactionMap);
        }

        $this->info(sprintf('    %s → expansion %d: %d recipes (max skill: %d)', $skillTierName, $expansionId, $count, $maxSkillLevel));

        return ['count' => $count, 'expansion_id' => $expansionId, 'max_skill_level' => $maxSkillLevel];
    }

    /**
     * @param  array{name: string, recipes?: list<array{id: int, name: string}>}  $category
     * @param  array<int, int>  $recipeSpellMap
     * @param  array<int, string>  $recipeFactionMap
     */
    private function importCategoryRecipes(
        array $category,
        int $professionId,
        int $expansionId,
        array $recipeSpellMap,
        array $recipeFactionMap,
    ): int {
        $categoryName = $category['name'];
        /** @var list<array{id: int, name: string|null}> $recipes */
        $recipes = $category['recipes'] ?? [];
        $count = 0;

        foreach ($recipes as $recipe) {
            $recipeName = $recipe['name'] ?? '';
            if ($recipeName === '') {
                continue;
            }

            WowRecipe::query()->updateOrCreate(['id' => $recipe['id']], [
                'name_fr' => $recipeName,
                'profession_id' => $professionId,
                'expansion_id' => $expansionId,
                'category_name' => $categoryName,
                'faction' => $recipeFactionMap[$recipe['id']] ?? null,
                'wowhead_spell_id' => $recipeSpellMap[$recipe['id']] ?? null,
                'is_active' => true,
            ]);
            $count++;
        }

        return $count;
    }

    public function tagMirrorRecipeFactions(): void
    {
        $this->info('Tagging mirror recipe pairs...');

        /** @var \Illuminate\Support\Collection<int, WowRecipe> $allRecipes */
        $allRecipes = WowRecipe::query()
            ->where('is_active', true)
            ->get(['id', 'name_fr', 'profession_id', 'expansion_id', 'faction']);

        /** @var array<string, list<array{id: int, faction: string|null}>> $groups */
        $groups = [];
        foreach ($allRecipes as $allRecipe) {
            $key = $allRecipe->name_fr.'|||'.$allRecipe->profession_id.'|||'.$allRecipe->expansion_id;
            $groups[$key][] = ['id' => $allRecipe->id, 'faction' => $allRecipe->faction];
        }

        $tagged = 0;
        foreach ($groups as $group) {
            if (count($group) !== 2) {
                continue;
            }

            $tagged += $this->tagMirrorPair($group[0], $group[1]);
        }

        $this->info(sprintf('Mirror recipe tagging complete: %d tagged.', $tagged));
    }

    /**
     * @param  array{id: int, faction: string|null}  $a
     * @param  array{id: int, faction: string|null}  $b
     */
    private function tagMirrorPair(array $a, array $b): int
    {
        if ($a['faction'] !== null && $b['faction'] === null) {
            $opposite = $a['faction'] === 'Alliance' ? 'Horde' : 'Alliance';
            WowRecipe::query()->where('id', $b['id'])->update(['faction' => $opposite]);

            return 1;
        }

        if ($b['faction'] !== null && $a['faction'] === null) {
            $opposite = $b['faction'] === 'Alliance' ? 'Horde' : 'Alliance';
            WowRecipe::query()->where('id', $a['id'])->update(['faction' => $opposite]);

            return 1;
        }

        return 0;
    }
}
