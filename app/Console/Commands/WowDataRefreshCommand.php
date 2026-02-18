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

    public function handle(BlizzardBatchImporter $blizzardBatchImporter, LuaAddonParser $luaAddonParser): void
    {
        /** @var string $type */
        $type = $this->option('type');

        if (!$this->option('force') && !$this->confirm('This will DELETE all existing data and re-import from scratch. Continue?')) {
            $this->info('Aborted.');
            return;
        }

        $this->info(sprintf('Starting WoW Data Refresh (type: %s)', (string) $type));
        $this->newLine();

        if ($type === 'all' || $type === 'achievements') {
            $achievementExpansionMap = $luaAddonParser->getAchievementExpansionMap();
            $this->info("Truncating wow_achievements...");
            WowAchievement::truncate();
            $blizzardBatchImporter->importAchievements($achievementExpansionMap);
            $this->newLine();
        }

        if ($type === 'all' || $type === 'quests') {
            $this->info("Building area→expansion map from DB2 data (AreaTable + Map + ContentTuning)...");
            $areaExpansionMap = $luaAddonParser->buildAreaExpansionMap();
            $modernQuestOverrides = $luaAddonParser->getQuestExpansionMap();
            $questFactionMap = $luaAddonParser->getQuestFactionMap();
            $zoneFactionMap = $luaAddonParser->getZoneFactionMap();
            $this->info("Truncating wow_quests...");
            WowQuest::truncate();
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
            $this->info("Truncating wow_mounts...");
            WowMount::truncate();
            $blizzardBatchImporter->importMounts();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'pets') {
            $this->info("Truncating wow_pets...");
            WowPet::truncate();
            $blizzardBatchImporter->importPets();
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
