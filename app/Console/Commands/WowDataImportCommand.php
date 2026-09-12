<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Blizzard\ImportBuildGate;
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
    protected $signature = 'app:wow-data-import {--type=all} {--force : Reimport even when the WoW build has not changed} {--full : Re-fetch every appearance instead of only the missing ones} {--limit= : Cap the number of appearance details fetched (smoke-test)}';

    protected $description = 'Import WoW data from SimpleArmory JSON + DB2 CSVs (and Blizzard API for quest mirrors)';

    /** @var list<string> */
    private const ENTITIES = ['achievements', 'quests', 'mounts', 'pets', 'professions', 'decor', 'appearances'];

    /** @var list<string> */
    private array $pending = [];

    private ?string $currentBuild = null;

    private ImportBuildGate $importBuildGate;

    public function handle(
        BlizzardBatchImporter $blizzardBatchImporter,
        LuaAddonParser $luaAddonParser,
        BlizzardApiClient $blizzardApiClient,
        ImportBuildGate $importBuildGate,
    ): void {
        ini_set('memory_limit', '1024M');

        $this->importBuildGate = $importBuildGate;

        /** @var string $type */
        $type = $this->option('type');

        $this->currentBuild = $blizzardApiClient->currentBuild();
        $this->pending = $this->entitiesToImport($type);

        if ($this->pending === []) {
            $this->info(sprintf(
                'Build %s already imported for every requested entity. Nothing to do — pass --force to reimport anyway.',
                $this->currentBuild ?? 'unknown',
            ));

            return;
        }

        $this->info(sprintf('Starting WoW Data Import (type: %s, build: %s)', $type, $this->currentBuild ?? 'unknown'));
        $this->newLine();

        if ($this->isPending('achievements')) {
            $this->info('Importing Achievements from SimpleArmory + Blizzard API...');
            $blizzardBatchImporter->importAchievements();
            $this->markImported('achievements');
            $this->newLine();
        }

        if ($this->isPending('quests')) {
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
            $this->markImported('quests');
            $this->newLine();
        }

        if ($this->isPending('mounts')) {
            $this->info('Importing Mounts from SimpleArmory + Blizzard API...');
            $blizzardBatchImporter->importMounts();
            $this->markImported('mounts');
            $this->newLine();
        }

        if ($this->isPending('pets')) {
            $this->info('Importing Pets from SimpleArmory + Blizzard API...');
            $blizzardBatchImporter->importPets();
            $this->markImported('pets');
            $this->newLine();
        }

        if ($this->isPending('professions')) {
            $recipeFactionMap = $luaAddonParser->getRecipeFactionMap();
            $this->info(sprintf('Importing Professions from Blizzard API (factions: %d)...', count($recipeFactionMap)));
            $blizzardBatchImporter->importProfessions($recipeFactionMap);
            $blizzardBatchImporter->tagMirrorRecipeFactions();
            $this->markImported('professions');
            $this->newLine();
        }

        if ($this->isPending('decor')) {
            $this->info('Importing Decor from SimpleArmory + Blizzard API...');
            $blizzardBatchImporter->importDecor();
            $this->markImported('decor');
            $this->newLine();
        }

        if ($this->isPending('appearances')) {
            $jobId = (string) \Illuminate\Support\Str::uuid();
            $this->info('Dispatching resumable appearance import (queue: imports)...');
            dispatch(new \App\Jobs\ImportAppearancesJob($jobId, (bool) $this->option('full')));
            $this->markImported('appearances');
            $this->newLine();
        }

        $this->info('Import Complete!');
        $this->displayStats();
    }

    private function isPending(string $entity): bool
    {
        return in_array($entity, $this->pending, true);
    }

    /**
     * @return list<string>
     */
    private function entitiesToImport(string $type): array
    {
        $requested = array_values(array_filter(
            self::ENTITIES,
            static fn (string $entity): bool => $type === 'all' || $type === $entity,
        ));

        if ($this->option('force')) {
            return $requested;
        }

        return array_values(array_filter(
            $requested,
            fn (string $entity): bool => ! $this->importBuildGate->isUpToDate($entity, $this->currentBuild),
        ));
    }

    private function markImported(string $entity): void
    {
        if ($this->currentBuild !== null) {
            $this->importBuildGate->remember($entity, $this->currentBuild);
        }
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
