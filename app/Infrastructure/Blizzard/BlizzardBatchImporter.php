<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use App\Infrastructure\Blizzard\Importers\AchievementImporter;
use App\Infrastructure\Blizzard\Importers\DecorImporter;
use App\Infrastructure\Blizzard\Importers\MountImporter;
use App\Infrastructure\Blizzard\Importers\PetImporter;
use App\Infrastructure\Blizzard\Importers\ProfessionImporter;
use App\Infrastructure\Blizzard\Importers\QuestImporter;

class BlizzardBatchImporter
{
    public function __construct(
        private readonly AchievementImporter $achievementImporter,
        private readonly QuestImporter $questImporter,
        private readonly MountImporter $mountImporter,
        private readonly PetImporter $petImporter,
        private readonly DecorImporter $decorImporter,
        private readonly ProfessionImporter $professionImporter,
    ) {}

    /**
     * @param  array<int, int>  $addonExpansionMap
     */
    public function importAchievements(array $addonExpansionMap = []): void
    {
        $this->achievementImporter->import($addonExpansionMap);
    }

    /**
     * @param  array<int, int>  $questExpansionMap
     * @param  array<int, string>  $questZoneMap
     * @param  array<int, string>  $questFactionMap
     */
    public function importQuests(
        array $questExpansionMap,
        array $questZoneMap = [],
        array $questFactionMap = [],
    ): void {
        $this->questImporter->import($questExpansionMap, $questZoneMap, $questFactionMap);
    }

    public function importMounts(): void
    {
        $this->mountImporter->import();
    }

    /**
     * @param  array<int, string>  $spellNameMap
     */
    public function importPets(array $spellNameMap = []): void
    {
        $this->petImporter->import($spellNameMap);
    }

    /**
     * @param  array<int, array{category: string, source: string}>  $categoryMap
     */
    public function importMountCategories(array $categoryMap): void
    {
        $this->mountImporter->importCategories($categoryMap);
    }

    public function importMountIcons(): void
    {
        $this->mountImporter->importIcons();
    }

    public function importPetIcons(): void
    {
        $this->petImporter->importIcons();
    }

    public function importDecor(): void
    {
        $this->decorImporter->import();
    }

    /**
     * @param  array<int, array{category: string, source: string}>  $categoryMap
     */
    public function importDecorCategories(array $categoryMap): void
    {
        $this->decorImporter->importCategories($categoryMap);
    }

    public function importDecorIcons(): void
    {
        $this->decorImporter->importIcons();
    }

    /**
     * @param  array<int, string>  $reputationFactionMap
     */
    public function tagMirrorQuestFactions(array $reputationFactionMap): void
    {
        $this->questImporter->tagMirrorFactions($reputationFactionMap);
    }

    public function tagMirrorRecipeFactions(): void
    {
        $this->professionImporter->tagMirrorRecipeFactions();
    }

    /**
     * @param  array<int, string>  $spellNameMap
     * @param  array<int, string>  $recipeFactionMap
     */
    public function importProfessions(array $spellNameMap = [], array $recipeFactionMap = []): void
    {
        $this->professionImporter->import($spellNameMap, $recipeFactionMap);
    }
}
