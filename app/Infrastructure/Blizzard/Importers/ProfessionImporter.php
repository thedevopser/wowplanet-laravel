<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Parsers\Db2ProfessionMapper;
use App\Models\WowProfession;
use App\Models\WowRecipe;
use Illuminate\Support\Facades\Log;

class ProfessionImporter
{
    private function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }

        Log::info($message);
    }

    /**
     * @param  array<int, string>  $spellNameMap  [spellId => spellName]
     * @param  array<int, string>  $recipeFactionMap  [recipeId => 'Alliance'|'Horde']
     */
    public function import(array $spellNameMap = [], array $recipeFactionMap = []): void
    {
        $this->info('Loading professions and recipes from DB2 CSV data...');
        $this->info(sprintf('  Spell name map: %d entries', count($spellNameMap)));
        $this->info(sprintf('  Recipe faction map: %d entries', count($recipeFactionMap)));

        $data = Db2ProfessionMapper::build($spellNameMap);
        if ($data['professions'] === []) {
            $this->info('ERROR: No professions found in skill_line.csv.');

            return;
        }

        $this->info(sprintf('Found %d professions, %d recipes.', count($data['professions']), count($data['recipes'])));

        // Save professions
        foreach ($data['professions'] as $profession) {
            WowProfession::query()->updateOrCreate(['id' => $profession['id']], [
                'name_fr' => $profession['name_fr'],
                'type' => $profession['type'],
                'max_skill_levels' => [],
                'is_active' => true,
            ]);
        }

        $this->info(sprintf('Saved %d professions.', count($data['professions'])));

        // Save recipes
        $count = 0;
        foreach ($data['recipes'] as $recipe) {
            WowRecipe::query()->updateOrCreate(['id' => $recipe['id']], [
                'name_fr' => $recipe['name_fr'],
                'profession_id' => $recipe['profession_id'],
                'expansion_id' => $recipe['expansion_id'],
                'category_name' => $recipe['category_name'],
                'faction' => $recipeFactionMap[$recipe['id']] ?? null,
                'wowhead_spell_id' => $recipe['wowhead_spell_id'],
                'is_active' => true,
            ]);
            $count++;
            if ($count % 2000 === 0) {
                $this->info(sprintf('  Saved %d recipes...', $count));
            }
        }

        $this->info(sprintf('Profession import complete: %d professions, %d recipes.', count($data['professions']), $count));
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
