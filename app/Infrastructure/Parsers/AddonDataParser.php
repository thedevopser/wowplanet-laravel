<?php

declare(strict_types=1);

namespace App\Infrastructure\Parsers;

use App\Infrastructure\Blizzard\Support\Db2CsvLoader;
use Illuminate\Support\Facades\File;

class AddonDataParser
{
    private const ALLIANCE_BITMASK = '6130900294268439629';

    private const HORDE_BITMASK = '-6184943489809468494';

    private const FACTION_BITMASK_MAP = [
        self::ALLIANCE_BITMASK => 'Alliance',
        self::HORDE_BITMASK => 'Horde',
    ];

    /**
     * Known Alliance race IDs (bitmask positions).
     * Human=1, Dwarf=3, NightElf=4, Gnome=7, Draenei=11, Worgen=22, Pandaren(A)=25,
     * VoidElf=29, LightforgedDraenei=30, DarkIronDwarf=34, KulTiran=36, Mechagnome=37.
     *
     * @var list<int>
     */
    private const ALLIANCE_RACE_IDS = [1, 3, 4, 7, 11, 22, 25, 29, 30, 34, 36, 37];

    /**
     * Known Horde race IDs (bitmask positions).
     * Orc=2, Undead=5, Tauren=6, Troll=8, BloodElf=10, Goblin=9, Pandaren(H)=26,
     * Nightborne=27, HighmountainTauren=28, MagharOrc=35, ZandalariTroll=31, Vulpera=33.
     *
     * @var list<int>
     */
    private const HORDE_RACE_IDS = [2, 5, 6, 8, 9, 10, 26, 27, 28, 31, 33, 35];

    private const STORMWIND_FACTION_ID = 72;

    /**
     * Cache for single-pass quest CSV parsing.
     *
     * @var array{quests: list<array{id: int, name_fr: string}>, expansionMap: array<int, int>, factionMap: array<int, string>}|null
     */
    private ?array $questCsvCache = null;

    /**
     * Parse quest_v2_cli_task.csv in a single pass, extracting quests, expansion map,
     * and faction map simultaneously.
     *
     * @return array{quests: list<array{id: int, name_fr: string}>, expansionMap: array<int, int>, factionMap: array<int, string>}
     */
    public function parseQuestCsvFull(): array
    {
        if ($this->questCsvCache !== null) {
            return $this->questCsvCache;
        }

        $csvPath = storage_path('app/blizzard/quest_v2_cli_task.csv');
        if (! File::exists($csvPath)) {
            return $this->questCsvCache = ['quests' => [], 'expansionMap' => [], 'factionMap' => []];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return $this->questCsvCache = ['quests' => [], 'expansionMap' => [], 'factionMap' => []];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);

            return $this->questCsvCache = ['quests' => [], 'expansionMap' => [], 'factionMap' => []];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $nameIdx = (int) array_search('QuestTitle_lang', $headers, true);
        $ctIdx = (int) array_search('ContentTuningID', $headers, true);
        $racesIdx = (int) array_search('FiltRaces', $headers, true);

        $contentTuningMap = Db2CsvLoader::loadMapByHeaders('content_tuning.csv', 'ID', 'ExpansionID');

        $quests = [];
        $expansionMap = [];
        $factionMap = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $id = (int) $row[$idIdx];
            $name = trim($row[$nameIdx] ?? '');

            if ($name === '') {
                continue;
            }

            $quests[] = ['id' => $id, 'name_fr' => $name];

            // Expansion from ContentTuning
            $ctId = (int) ($row[$ctIdx] ?? 0);
            if ($ctId > 0 && isset($contentTuningMap[$ctId])) {
                $expansionMap[$id] = $contentTuningMap[$ctId];
            }

            // Faction from FiltRaces bitmask
            $racesMask = trim($row[$racesIdx] ?? '');
            $faction = self::FACTION_BITMASK_MAP[$racesMask] ?? $this->detectFactionFromRaceBitmask($racesMask);
            if ($faction !== null) {
                $factionMap[$id] = $faction;
            }
        }

        fclose($handle);

        return $this->questCsvCache = [
            'quests' => $quests,
            'expansionMap' => $expansionMap,
            'factionMap' => $factionMap,
        ];
    }

    /**
     * Build quest_id → expansion_id map from ContentTuning for ALL expansions.
     *
     * @return array<int, int>
     */
    public function getQuestExpansionMap(): array
    {
        return $this->parseQuestCsvFull()['expansionMap'];
    }

    /**
     * Parse QuestV2CliTask CSV for quest_id → faction from FiltRaces bitmask.
     *
     * @return array<int, string>
     */
    public function getQuestFactionMap(): array
    {
        return $this->parseQuestCsvFull()['factionMap'];
    }

    /**
     * Get parsed quest list from CSV (single pass).
     *
     * @return list<array{id: int, name_fr: string}>
     */
    public function getQuestList(): array
    {
        return $this->parseQuestCsvFull()['quests'];
    }

    /**
     * Parse SkillLineAbility CSV for recipe_id → faction from RaceMask bitmask.
     *
     * @return array<int, string>
     */
    public function getRecipeFactionMap(): array
    {
        return $this->parseFactionBitmask('skill_line_ability.csv', 'ID', 'RaceMask');
    }

    /**
     * Build area_id → faction map from AreaTable FactionGroupMask.
     *
     * @return array<int, string>
     */
    public function getZoneFactionMap(): array
    {
        $csvPath = storage_path('app/blizzard/area_table.csv');
        if (! File::exists($csvPath)) {
            return [];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $fgmIdx = (int) array_search('FactionGroupMask', $headers, true);

        /** @var array<int, string> $fgmFactionMap */
        $fgmFactionMap = [2 => 'Alliance', 4 => 'Horde'];

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $faction = $fgmFactionMap[(int) $row[$fgmIdx]] ?? null;
            if ($faction !== null) {
                $map[(int) $row[$idIdx]] = $faction;
            }
        }

        fclose($handle);

        return $map;
    }

    /**
     * Build reputation_faction_id → 'Alliance'|'Horde' map from Faction.csv.
     *
     * @return array<int, string>
     */
    public function getReputationFactionMap(): array
    {
        $csvPath = storage_path('app/blizzard/faction.csv');
        if (! File::exists($csvPath)) {
            return [];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $mask0Idx = (int) array_search('ReputationRaceMask_0', $headers, true);
        $max0Idx = (int) array_search('ReputationMax_0', $headers, true);
        $max1Idx = (int) array_search('ReputationMax_1', $headers, true);

        $allianceRefMask = 0;
        /** @var array<int, int> $exclusiveFactions faction_id → mask_0 */
        $exclusiveFactions = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $id = (int) $row[$idIdx];
            $mask0 = (int) $row[$mask0Idx];
            $max0 = (int) $row[$max0Idx];
            $max1 = (int) $row[$max1Idx];

            if ($id === self::STORMWIND_FACTION_ID) {
                $allianceRefMask = $mask0;
            }

            if ($max1 < 0 && $max0 > 0) {
                $exclusiveFactions[$id] = $mask0;
            }
        }

        fclose($handle);

        if ($allianceRefMask === 0) {
            return [];
        }

        $map = [];
        foreach ($exclusiveFactions as $factionId => $mask0) {
            $map[$factionId] = ($allianceRefMask & $mask0) !== 0 ? 'Alliance' : 'Horde';
        }

        return $map;
    }

    /**
     * Build a zone_name (lowercase) → expansion_id map from supplementary config.
     *
     * @return array<string, int>
     */
    public function getZoneExpansionMap(): array
    {
        $map = [];

        /** @var array<string, int> $supplementaryZones */
        $supplementaryZones = (array) config('wow_zones', []);
        foreach ($supplementaryZones as $zoneName => $expansionId) {
            $map[mb_strtolower(LuaAddonParser::normalizeApostrophes($zoneName))] = $expansionId;
        }

        return $map;
    }

    /**
     * Detect faction from a non-standard race bitmask by checking if all
     * set race bits belong to one faction.
     */
    private function detectFactionFromRaceBitmask(string $bitmaskStr): ?string
    {
        if (in_array($bitmaskStr, ['', '-1', '0'], true)) {
            return null;
        }

        // For small positive bitmasks, check if all set bits are one faction
        $mask = (int) $bitmaskStr;
        if ($mask <= 0) {
            return null;
        }

        $hasAlliance = false;
        $hasHorde = false;

        foreach (self::ALLIANCE_RACE_IDS as $raceId) {
            if (($mask & (1 << ($raceId - 1))) !== 0) {
                $hasAlliance = true;
            }
        }

        foreach (self::HORDE_RACE_IDS as $raceId) {
            if (($mask & (1 << ($raceId - 1))) !== 0) {
                $hasHorde = true;
            }
        }

        if ($hasAlliance && ! $hasHorde) {
            return 'Alliance';
        }

        if ($hasHorde && ! $hasAlliance) {
            return 'Horde';
        }

        return null;
    }

    /**
     * Parse a CSV file and extract faction from a bitmask column.
     *
     * @return array<int, string>
     */
    private function parseFactionBitmask(string $filename, string $idHeader, string $bitmaskHeader): array
    {
        $csvPath = storage_path('app/blizzard/'.$filename);
        if (! File::exists($csvPath)) {
            return [];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $idIdx = (int) array_search($idHeader, $headers, true);
        $bitmaskIdx = (int) array_search($bitmaskHeader, $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $bitmask = trim((string) $row[$bitmaskIdx]);
            $faction = self::FACTION_BITMASK_MAP[$bitmask] ?? $this->detectFactionFromRaceBitmask($bitmask);
            if ($faction !== null) {
                $map[(int) $row[$idIdx]] = $faction;
            }
        }

        fclose($handle);

        return $map;
    }
}
