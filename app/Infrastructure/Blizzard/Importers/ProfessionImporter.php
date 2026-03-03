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

        // Save professions (bulk upsert)
        WowProfession::query()->upsert(
            array_map(fn (array $p): array => [
                'id' => $p['id'],
                'name_fr' => $p['name_fr'],
                'type' => $p['type'],
                'max_skill_levels' => json_encode([]),
                'is_active' => true,
            ], $data['professions']),
            uniqueBy: ['id'],
            update: ['name_fr', 'type', 'is_active'],
        );

        $this->info(sprintf('Saved %d professions.', count($data['professions'])));

        // Save recipes (bulk upsert in chunks)
        $count = 0;
        foreach (array_chunk($data['recipes'], 500) as $chunk) {
            WowRecipe::query()->upsert(
                array_map(fn (array $r): array => [
                    'id' => $r['id'],
                    'name_fr' => $r['name_fr'],
                    'profession_id' => $r['profession_id'],
                    'expansion_id' => $r['expansion_id'],
                    'category_name' => $r['category_name'],
                    'faction' => $recipeFactionMap[$r['id']] ?? null,
                    'wowhead_spell_id' => $r['wowhead_spell_id'],
                    'is_active' => true,
                ], $chunk),
                uniqueBy: ['id'],
                update: ['name_fr', 'profession_id', 'expansion_id', 'category_name', 'faction', 'wowhead_spell_id', 'is_active'],
            );
            $count += count($chunk);
            if ($count % 2000 === 0) {
                $this->info(sprintf('  Saved %d recipes...', $count));
            }
        }

        $this->info(sprintf('Profession import complete: %d professions, %d recipes.', count($data['professions']), $count));
    }

    public function tagMirrorRecipeFactions(): void
    {
        $this->info('Tagging mirror recipe pairs...');

        /** @var array<string, list<array{id: int, faction: string|null}>> $groups */
        $groups = [];
        foreach (WowRecipe::query()->where('is_active', true)->lazy() as $lazyCollection) {
            $key = $lazyCollection->name_fr.'|||'.$lazyCollection->profession_id.'|||'.$lazyCollection->expansion_id;
            $groups[$key][] = ['id' => $lazyCollection->id, 'faction' => $lazyCollection->faction];
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
