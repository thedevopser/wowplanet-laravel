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
     * @param  array<int, int>  $areaExpansionMap
     * @param  array<int, int>  $modernQuestOverrides
     * @param  array<int, string>  $questFactionMap
     * @param  array<int, string>  $zoneFactionMap
     */
    public function importQuests(
        array $areaExpansionMap,
        array $modernQuestOverrides = [],
        array $questFactionMap = [],
        array $zoneFactionMap = [],
    ): void {
        $this->questImporter->import($areaExpansionMap, $modernQuestOverrides, $questFactionMap, $zoneFactionMap);
    }

    public function importMounts(): void
    {
        $this->mountImporter->import();
    }

    public function importPets(): void
    {
        $this->petImporter->import();
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
     * @param  array<int, string>  $recipeFactionMap
     */
    public function importProfessions(array $recipeFactionMap = []): void
    {
        $this->professionImporter->import($recipeFactionMap);
    }
}
