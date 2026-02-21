<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Parsers\DecorCategoryMapper;
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

    protected $description = 'Import WoW data from DB2 CSVs and Blizzard API (icons, decor, mirror factions)';

    public function handle(BlizzardBatchImporter $blizzardBatchImporter, LuaAddonParser $luaAddonParser): void
    {
        /** @var string $type */
        $type = $this->option('type');

        $this->info(sprintf('Starting WoW Data Import (type: %s)', $type));
        $this->newLine();

        /** @var array<int, string>|null $spellNameMap */
        $spellNameMap = null;

        if ($type === 'all' || $type === 'achievements') {
            $achievementExpansionMap = $luaAddonParser->getAchievementExpansionMap();
            $this->info(sprintf('Importing Achievements from DB2 CSV (expansion map: %d IDs)...', count($achievementExpansionMap)));
            $blizzardBatchImporter->importAchievements($achievementExpansionMap);
            $this->newLine();
        }

        if ($type === 'all' || $type === 'quests') {
            $questExpansionMap = $luaAddonParser->getQuestExpansionMap();
            $questZoneMap = $luaAddonParser->getQuestZoneMap();
            $questFactionMap = $luaAddonParser->getQuestFactionMap();
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
            $this->info('Importing Mounts from DB2 CSV...');
            $blizzardBatchImporter->importMounts();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'pets') {
            $spellNameMap ??= $luaAddonParser->getSpellNameMap();
            $this->info(sprintf('Importing Pets from DB2 CSV (spell names: %d)...', count($spellNameMap)));
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

        if ($type === 'icons' || $type === 'mount-icons') {
            $this->info('Fetching Mount Icons...');
            $blizzardBatchImporter->importMountIcons();
            $this->newLine();
        }

        if ($type === 'icons' || $type === 'pet-icons') {
            $this->info('Fetching Pet Icons...');
            $blizzardBatchImporter->importPetIcons();
            $this->newLine();
        }

        if ($type === 'all' || $type === 'decor') {
            $this->info('Importing Decor...');
            $blizzardBatchImporter->importDecor();
            $decorCategoryMap = DecorCategoryMapper::build();
            $this->info(sprintf('Decor category map: %d IDs', count($decorCategoryMap)));
            $blizzardBatchImporter->importDecorCategories($decorCategoryMap);
            $this->newLine();
        }

        if ($type === 'icons' || $type === 'decor-icons') {
            $this->info('Fetching Decor Icons...');
            $blizzardBatchImporter->importDecorIcons();
            $this->newLine();
        }

        $this->info('Import Complete!');
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
            ]
        );
    }
}
