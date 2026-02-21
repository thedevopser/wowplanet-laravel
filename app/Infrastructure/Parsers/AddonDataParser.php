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

    private const STORMWIND_FACTION_ID = 72;

    /**
     * Build quest_id → expansion_id map from ContentTuning for ALL expansions.
     * Uses DB2 QuestV2CliTask.ContentTuningID + ContentTuning.ExpansionID.
     *
     * @return array<int, int>
     */
    public function getQuestExpansionMap(): array
    {
        $contentTuningMap = Db2CsvLoader::loadMapByHeaders('content_tuning.csv', 'ID', 'ExpansionID');
        $questContentTuning = $this->parseQuestV2CliTaskContentTuning();

        $map = [];
        foreach ($questContentTuning as $questId => $contentTuningId) {
            if (! isset($contentTuningMap[$contentTuningId])) {
                continue;
            }

            $map[$questId] = $contentTuningMap[$contentTuningId];
        }

        return $map;
    }

    /**
     * Parse QuestV2CliTask CSV for quest_id → faction from FiltRaces bitmask.
     *
     * @return array<int, string>
     */
    public function getQuestFactionMap(): array
    {
        return $this->parseFactionBitmask('quest_v2_cli_task.csv', 'ID', 'FiltRaces');
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
     * Exclusive factions are detected by ReputationMax_1 < 0 (one group of races
     * can never gain reputation). Alliance/Horde is determined by comparing each
     * faction's ReputationRaceMask_0 against Stormwind's (ID 72) — if the masks
     * overlap, group 0 contains Alliance races.
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
            $faction = self::FACTION_BITMASK_MAP[trim((string) $row[$bitmaskIdx])] ?? null;
            if ($faction !== null) {
                $map[(int) $row[$idIdx]] = $faction;
            }
        }

        fclose($handle);

        return $map;
    }

    /**
     * Parse QuestV2CliTask CSV for quest_id → contentTuningID.
     *
     * @return array<int, int>
     */
    private function parseQuestV2CliTaskContentTuning(): array
    {
        $csvPath = storage_path('app/blizzard/quest_v2_cli_task.csv');
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
        $ctIdx = (int) array_search('ContentTuningID', $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $ctId = (int) $row[$ctIdx];
            if ($ctId <= 0) {
                continue;
            }

            $map[(int) $row[$idIdx]] = $ctId;
        }

        fclose($handle);

        return $map;
    }
}
