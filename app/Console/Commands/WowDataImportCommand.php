<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Parsers\LuaAddonParser;
use App\Models\WowAchievement;
use App\Models\WowAppearance;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use Illuminate\Console\Command;

class WowDataImportCommand extends Command
{
    protected $signature = 'app:wow-data-import {--type=all} {--full : Re-fetch every appearance instead of only the missing ones} {--limit= : Cap the number of appearance details fetched (smoke-test)}';

    protected $description = 'Import WoW data from SimpleArmory JSON + DB2 CSVs (and Blizzard API for quest mirrors)';

    public function handle(BlizzardBatchImporter $blizzardBatchImporter, LuaAddonParser $luaAddonParser): void
    {
        ini_set('memory_limit', '1024M');

        /** @var string $type */
        $type = $this->option('type');

        $this->info(sprintf('Starting WoW Data Import (type: %s)', $type));
        $this->newLine();

        if ($type === 'all' || $type === 'achievements') {
            $this->info('Importing Achievements from SimpleArmory + Blizzard API...');
            $blizzardBatchImporter->importAchievements();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'quests') {
            $this->info('Loading frozen area→expansion map...');
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
            $this->info('Importing Mounts from SimpleArmory + Blizzard API...');
            $blizzardBatchImporter->importMounts();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'pets') {
            $this->info('Importing Pets from SimpleArmory + Blizzard API...');
            $blizzardBatchImporter->importPets();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'professions') {
            $recipeFactionMap = $luaAddonParser->getRecipeFactionMap();
            $this->info(sprintf('Importing Professions from Blizzard API (factions: %d)...', count($recipeFactionMap)));
            $blizzardBatchImporter->importProfessions($recipeFactionMap);
            $blizzardBatchImporter->tagMirrorRecipeFactions();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'decor') {
            $this->info('Importing Decor from SimpleArmory + Blizzard API...');
            $blizzardBatchImporter->importDecor();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'appearances') {
            $jobId = (string) \Illuminate\Support\Str::uuid();
            $this->info('Dispatching resumable appearance import (queue: imports)...');
            dispatch(new \App\Jobs\ImportAppearancesJob($jobId, (bool) $this->option('full')));
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
                ['Appearances', WowAppearance::query()->count(), WowAppearance::query()->where('is_active', true)->count(), WowAppearance::query()->whereNotNull('icon_url')->count()],
            ]
        );
    }
}
