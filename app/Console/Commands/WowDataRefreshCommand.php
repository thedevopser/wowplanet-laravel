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

class WowDataRefreshCommand extends Command
{
    protected $signature = 'app:wow-data-refresh {--type=all} {--force}';
    protected $description = 'Truncate and re-import WoW data from Blizzard API';

    public function handle(BlizzardBatchImporter $importer, LuaAddonParser $addonParser): void
    {
        $type = $this->option('type');

        if (!$this->option('force') && !$this->confirm('This will DELETE all existing data and re-import from scratch. Continue?')) {
            $this->info('Aborted.');
            return;
        }

        $this->info("Starting WoW Data Refresh (type: {$type})");
        $this->newLine();

        if ($type === 'all' || $type === 'achievements') {
            $achievementExpansionMap = $addonParser->getAchievementExpansionMap();
            $this->info("Truncating wow_achievements...");
            WowAchievement::truncate();
            $importer->importAchievements($achievementExpansionMap);
            $this->newLine();
        }

        if ($type === 'all' || $type === 'quests') {
            $this->info("Building area→expansion map from DB2 data (AreaTable + Map + ContentTuning)...");
            $areaExpansionMap = $addonParser->buildAreaExpansionMap();
            $modernQuestOverrides = $addonParser->getQuestExpansionMap();
            $this->info("Truncating wow_quests...");
            WowQuest::truncate();
            $this->info("Importing Quests (DB2 areas: " . count($areaExpansionMap) . ", modern overrides: " . count($modernQuestOverrides) . ")...");
            $importer->importQuests($areaExpansionMap, $modernQuestOverrides);
            $this->newLine();
        }

        if ($type === 'all' || $type === 'mounts') {
            $this->info("Truncating wow_mounts...");
            WowMount::truncate();
            $importer->importMounts();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'pets') {
            $this->info("Truncating wow_pets...");
            WowPet::truncate();
            $importer->importPets();
            $this->newLine();
        }

        $this->info("Refresh Complete!");
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
