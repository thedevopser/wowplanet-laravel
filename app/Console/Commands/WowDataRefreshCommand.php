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

class WowDataRefreshCommand extends Command
{
    protected $signature = 'app:wow-data-refresh {--type=all} {--force}';

    protected $description = 'Truncate and re-import WoW data from Blizzard API';

    public function handle(BlizzardBatchImporter $blizzardBatchImporter, LuaAddonParser $luaAddonParser): void
    {
        ini_set('memory_limit', '512M');

        /** @var string $type */
        $type = $this->option('type');

        if (! $this->option('force') && ! $this->confirm('This will DELETE all existing data and re-import from scratch. Continue?')) {
            $this->info('Aborted.');

            return;
        }

        $this->info(sprintf('Starting WoW Data Refresh (type: %s)', (string) $type));
        $this->newLine();

        if ($type === 'all' || $type === 'achievements') {
            $this->info('Truncating wow_achievements...');
            WowAchievement::query()->truncate();
            $blizzardBatchImporter->importAchievements();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'quests') {
            $this->info('Building area→expansion map from DB2 data...');
            $areaExpansionMap = $luaAddonParser->buildAreaExpansionMap();
            $questExpansionMap = $luaAddonParser->getQuestExpansionMap();
            $questFactionMap = $luaAddonParser->getQuestFactionMap();
            $zoneFactionMap = $luaAddonParser->getZoneFactionMap();
            $this->info('Truncating wow_quests...');
            WowQuest::query()->truncate();
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
            $this->info('Truncating wow_mounts...');
            WowMount::query()->truncate();
            $blizzardBatchImporter->importMounts();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'pets') {
            $this->info('Truncating wow_pets...');
            WowPet::query()->truncate();
            $blizzardBatchImporter->importPets();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'professions') {
            $recipeFactionMap = $luaAddonParser->getRecipeFactionMap();
            $this->info('Truncating wow_professions and wow_recipes...');
            WowRecipe::query()->truncate();
            WowProfession::query()->truncate();
            $blizzardBatchImporter->importProfessions($recipeFactionMap);
            $blizzardBatchImporter->tagMirrorRecipeFactions();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'decor') {
            $this->info('Truncating wow_decors...');
            WowDecor::query()->truncate();
            $blizzardBatchImporter->importDecor();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'appearances') {
            $this->info('Truncating wow_appearances...');
            WowAppearance::query()->truncate();
            $blizzardBatchImporter->importAppearances();
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
                ['Professions', WowProfession::query()->count()],
                ['Recipes', WowRecipe::query()->count()],
                ['Decor', WowDecor::query()->count()],
                ['Appearances', WowAppearance::query()->count()],
            ]
        );
    }
}
