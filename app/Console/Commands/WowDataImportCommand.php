<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Parsers\LuaAddonParser;
use App\Models\WowAchievement;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use Illuminate\Console\Command;

class WowDataImportCommand extends Command
{
    protected $signature = 'app:wow-data-import {--type=all}';

    protected $description = 'Import WoW data from SimpleArmory JSON + DB2 CSVs (and Blizzard API for quest mirrors)';

    public function handle(BlizzardBatchImporter $blizzardBatchImporter, LuaAddonParser $luaAddonParser): void
    {
        ini_set('memory_limit', '512M');

        /** @var string $type */
        $type = $this->option('type');

        $this->info(sprintf('Starting WoW Data Import (type: %s)', $type));
        $this->newLine();

        /** @var array<int, string>|null $spellNameMap */
        $spellNameMap = null;

        if ($type === 'all' || $type === 'achievements') {
            $this->info('Importing Achievements from SimpleArmory + DB2...');
            $blizzardBatchImporter->importAchievements();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'quests') {
            $this->info('Building area→expansion map from DB2 data...');
            $areaExpansionMap = $luaAddonParser->buildAreaExpansionMap();
            $questExpansionMap = $luaAddonParser->getQuestExpansionMap();
            $questFactionMap = $luaAddonParser->getQuestFactionMap();
            $zoneFactionMap = $luaAddonParser->getZoneFactionMap();
            $this->info(sprintf(
                'Importing Quests from API (areas: %d, quest CT overrides: %d, faction quests: %d, faction zones: %d)...',
                count($areaExpansionMap),
                count($questExpansionMap),
                count($questFactionMap),
                count($zoneFactionMap),
            ));
            $blizzardBatchImporter->importQuests($areaExpansionMap, $questExpansionMap, $questFactionMap, $zoneFactionMap);
            $reputationFactionMap = $luaAddonParser->getReputationFactionMap();
            $blizzardBatchImporter->tagMirrorQuestFactions($reputationFactionMap);
            $this->newLine();
        }

        if ($type === 'all' || $type === 'mounts') {
            $this->info('Importing Mounts from SimpleArmory + DB2...');
            $blizzardBatchImporter->importMounts();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'pets') {
            $spellNameMap ??= $luaAddonParser->getSpellNameMap();
            $this->info(sprintf('Importing Pets from SimpleArmory + DB2 (spell names: %d)...', count($spellNameMap)));
            $blizzardBatchImporter->importPets($spellNameMap);
            $this->newLine();
        }

        if ($type === 'all' || $type === 'professions') {
            $spellNameMap ??= $luaAddonParser->getSpellNameMap();
            $recipeFactionMap = $luaAddonParser->getRecipeFactionMap();
            $this->info(sprintf(
                'Importing Professions from DB2 CSV (spell names: %d, factions: %d)...',
                count($spellNameMap),
                count($recipeFactionMap),
            ));
            $blizzardBatchImporter->importProfessions($spellNameMap, $recipeFactionMap);
            $blizzardBatchImporter->tagMirrorRecipeFactions();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'decor') {
            $this->info('Importing Decor from SimpleArmory + DB2...');
            $blizzardBatchImporter->importDecor();
            $this->newLine();
        }

        $this->info('Import Complete!');
        $this->displayStats();
    }

    private function displayStats(): void
    {
        $this->newLine();
        $this->table(
            ['Type', 'Total', 'Active', 'With Icon'],
            [
                ['Quests', WowQuest::query()->count(), WowQuest::query()->where('is_active', true)->count(), '—'],
                ['Achievements', WowAchievement::query()->count(), WowAchievement::query()->where('is_active', true)->count(), WowAchievement::query()->whereNotNull('icon_url')->count()],
                ['Mounts', WowMount::query()->count(), WowMount::query()->where('is_active', true)->count(), WowMount::query()->whereNotNull('icon_url')->count()],
                ['Pets', WowPet::query()->count(), WowPet::query()->where('is_active', true)->count(), WowPet::query()->whereNotNull('icon_url')->count()],
                ['Professions', WowProfession::query()->count(), WowProfession::query()->where('is_active', true)->count(), '—'],
                ['Recipes', WowRecipe::query()->count(), WowRecipe::query()->where('is_active', true)->count(), '—'],
                ['Decor', WowDecor::query()->count(), WowDecor::query()->where('is_active', true)->count(), WowDecor::query()->whereNotNull('icon_url')->count()],
            ]
        );
    }
}
