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

        if (! $this->option('force') && ! $this->confirm('This will DELETE all existing data and re-import from scratch. Continue?')) {
            $this->info('Aborted.');

            return;
        }

        $this->info(sprintf('Starting WoW Data Refresh (type: %s)', (string) $type));
        $this->newLine();

        if ($type === 'all' || $type === 'achievements') {
            $achievementExpansionMap = $luaAddonParser->getAchievementExpansionMap();
            $this->info('Truncating wow_achievements...');
            WowAchievement::query()->truncate();
            $blizzardBatchImporter->importAchievements($achievementExpansionMap);
            $this->newLine();
        }

        if ($type === 'all' || $type === 'quests') {
            $questExpansionMap = $luaAddonParser->getQuestExpansionMap();
            $questZoneMap = $luaAddonParser->getQuestZoneMap();
            $questFactionMap = $luaAddonParser->getQuestFactionMap();
            $this->info('Truncating wow_quests...');
            WowQuest::query()->truncate();
            $this->info(sprintf(
                'Importing Quests from DB2 CSV (expansion: %d, zones: %d, factions: %d)...',
                count($questExpansionMap),
                count($questZoneMap),
                count($questFactionMap),
            ));
            $blizzardBatchImporter->importQuests($questExpansionMap, $questZoneMap, $questFactionMap);
            $reputationFactionMap = $luaAddonParser->getReputationFactionMap();
            $blizzardBatchImporter->tagMirrorQuestFactions($reputationFactionMap);
            $this->newLine();
        }

        if ($type === 'all' || $type === 'mounts') {
            $this->info('Truncating wow_mounts...');
            WowMount::query()->truncate();
            $blizzardBatchImporter->importMounts();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'pets') {
            $spellNameMap = $luaAddonParser->getSpellNameMap();
            $this->info('Truncating wow_pets...');
            WowPet::query()->truncate();
            $blizzardBatchImporter->importPets($spellNameMap);
            $this->newLine();
        }

        $this->info('Refresh Complete!');
        $this->displayStats();
    }

    private function displayStats(): void
    {
        $this->newLine();
        $this->table(
            ['Type', 'Count'],
            [
                ['Quests', WowQuest::query()->count()],
                ['Achievements', WowAchievement::query()->count()],
                ['Mounts', WowMount::query()->count()],
                ['Pets', WowPet::query()->count()],
            ]
        );
    }
}
