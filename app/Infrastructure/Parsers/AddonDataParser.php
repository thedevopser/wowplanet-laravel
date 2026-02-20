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
     * Build reputation_faction_id → faction map from Faction.csv ReputationBase values.
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
        $base0Idx = (int) array_search('ReputationBase_0', $headers, true);
        $base1Idx = (int) array_search('ReputationBase_1', $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $faction = $this->resolveReputationFaction((int) $row[$base0Idx], (int) $row[$base1Idx]);
            if ($faction !== null) {
                $map[(int) $row[$idIdx]] = $faction;
            }
        }

        fclose($handle);

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

    private function resolveReputationFaction(int $allianceBase, int $hordeBase): ?string
    {
        if ($allianceBase >= 0 && $hordeBase < 0) {
            return 'Alliance';
        }

        if ($hordeBase >= 0 && $allianceBase < 0) {
            return 'Horde';
        }

        return null;
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
