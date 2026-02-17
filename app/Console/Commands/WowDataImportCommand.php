<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Parsers\LuaAddonParser;
use App\Models\WowAchievement;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowQuest;
use Illuminate\Console\Command;

class WowDataImportCommand extends Command
{
    protected $signature = 'app:wow-data-import {--type=all}';
    protected $description = 'Import WoW data from Blizzard API (quests, achievements, mounts, pets)';

    public function handle(BlizzardBatchImporter $importer, LuaAddonParser $addonParser): void
    {
        $type = $this->option('type');

        $this->info("Starting WoW Data Import (type: {$type})");
        $this->newLine();

        if ($type === 'all' || $type === 'achievements') {
            $achievementExpansionMap = $addonParser->getAchievementExpansionMap();
            $this->info("Importing Achievements (category tree + addon expansion mapping, " . count($achievementExpansionMap) . " mapped)...");
            $importer->importAchievements($achievementExpansionMap);
            $this->newLine();
        }

        if ($type === 'all' || $type === 'quests') {
            $this->info("Building area→expansion map from DB2 data (AreaTable + Map + ContentTuning)...");
            $areaExpansionMap = $addonParser->buildAreaExpansionMap();
            $modernQuestOverrides = $addonParser->getQuestExpansionMap();
            $this->info("Importing Quests (DB2 areas: " . count($areaExpansionMap) . ", modern overrides: " . count($modernQuestOverrides) . ")...");
            $importer->importQuests($areaExpansionMap, $modernQuestOverrides);
            $this->newLine();
        }

        if ($type === 'all' || $type === 'mounts') {
            $this->info("Importing Mounts...");
            $importer->importMounts();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'pets') {
            $this->info("Importing Pets...");
            $importer->importPets();
            $this->newLine();
        }

        $this->info("Import Complete!");
        $this->displayStats();
    }

    private function displayStats(): void
    {
        $this->newLine();
        $this->table(
            ['Type', 'Count'],
            [
                ['Quests', WowQuest::count()],
                ['Achievements', WowAchievement::count()],
                ['Mounts', WowMount::count()],
                ['Pets', WowPet::count()],
            ]
        );
    }
}
