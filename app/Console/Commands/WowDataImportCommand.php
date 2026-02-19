<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Parsers\LuaAddonParser;
use App\Models\WowAchievement;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use Illuminate\Console\Command;

class WowDataImportCommand extends Command
{
    protected $signature = 'app:wow-data-import {--type=all}';

    protected $description = 'Import WoW data from Blizzard API (quests, achievements, mounts, pets, professions)';

    public function handle(BlizzardBatchImporter $blizzardBatchImporter, LuaAddonParser $luaAddonParser): void
    {
        /** @var string $type */
        $type = $this->option('type');

        $this->info(sprintf('Starting WoW Data Import (type: %s)', $type));
        $this->newLine();

        if ($type === 'all' || $type === 'achievements') {
            $achievementExpansionMap = $luaAddonParser->getAchievementExpansionMap();
            $this->info(sprintf('Importing Achievements (category tree + addon expansion mapping, %d mapped)...', count($achievementExpansionMap)));
            $blizzardBatchImporter->importAchievements($achievementExpansionMap);
            $this->newLine();
        }

        if ($type === 'all' || $type === 'quests') {
            $this->info("Building area→expansion map from DB2 data (AreaTable + Map + ContentTuning)...");
            $areaExpansionMap = $luaAddonParser->buildAreaExpansionMap();
            $modernQuestOverrides = $luaAddonParser->getQuestExpansionMap();
            $questFactionMap = $luaAddonParser->getQuestFactionMap();
            $zoneFactionMap = $luaAddonParser->getZoneFactionMap();
            $this->info(sprintf(
                'Importing Quests (DB2 areas: %d, modern overrides: %d, faction quests: %d, faction zones: %d)...',
                count($areaExpansionMap),
                count($modernQuestOverrides),
                count($questFactionMap),
                count($zoneFactionMap),
            ));
            $blizzardBatchImporter->importQuests($areaExpansionMap, $modernQuestOverrides, $questFactionMap, $zoneFactionMap);
            $reputationFactionMap = $luaAddonParser->getReputationFactionMap();
            $blizzardBatchImporter->tagMirrorQuestFactions($reputationFactionMap);
            $this->newLine();
        }

        if ($type === 'all' || $type === 'mounts') {
            $this->info("Importing Mounts...");
            $blizzardBatchImporter->importMounts();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'pets') {
            $this->info("Importing Pets...");
            $blizzardBatchImporter->importPets();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'professions') {
            $this->info("Importing Professions and Recipes...");
            $blizzardBatchImporter->importProfessions();
            $this->newLine();
        }

        if ($type === 'icons' || $type === 'mount-icons') {
            $this->info("Fetching Mount Icons...");
            $blizzardBatchImporter->importMountIcons();
            $this->newLine();
        }

        if ($type === 'icons' || $type === 'pet-icons') {
            $this->info("Fetching Pet Icons...");
            $blizzardBatchImporter->importPetIcons();
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
                ['Professions', WowProfession::count()],
                ['Recipes', WowRecipe::count()],
            ]
        );
    }
}
