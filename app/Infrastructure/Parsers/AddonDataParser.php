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

    private const BTW_EXPANSION_DIRS = [
        'BtWQuestsClassic' => 0,
        'BtWQuestsTheBurningCrusade' => 1,
        'BtWQuestsWrathOfTheLichKing' => 2,
        'BtWQuestsCataclysm' => 3,
        'BtWQuestsMistsOfPandaria' => 4,
        'BtWQuestsWarlordsOfDraenor' => 5,
        'BtWQuestsLegion' => 6,
        'BtWQuestsBattleForAzeroth' => 7,
        'BtWQuestsBattleForAzerothPrologue' => 7,
        'BtWQuestsShadowlands' => 8,
        'BtWQuestsShadowlandsPrologue' => 8,
        'BtWQuestsDragonflight' => 9,
        'BtWQuestsDragonflightPrologue' => 9,
        'BtWQuestsTheWarWithin' => 10,
        'BtWQuestsMidnightPrologue' => 11,
    ];

    /**
     * Build an achievement_id → expansion_id map from addon total_ids.
     *
     * @return array<int, int>
     */
    public function getAchievementExpansionMap(): array
    {
        $achievementsPath = storage_path('app/blizzard/mappings/processed/achievements.json');
        if (! File::exists($achievementsPath)) {
            return [];
        }

        /** @var array<int|string, array{total_ids: list<int>}> $data */
        $data = json_decode(File::get($achievementsPath), true);
        $map = [];

        foreach ($data as $expansionId => $expansionData) {
            foreach ($expansionData['total_ids'] as $totalId) {
                $map[(int) $totalId] = (int) $expansionId;
            }
        }

        return $map;
    }

    /**
     * Build quest_id → expansion_id map for MODERN EXPANSION OVERRIDES ONLY (>= 10).
     *
     * @return array<int, int>
     */
    public function getQuestExpansionMap(): array
    {
        $contentTuningMap = Db2CsvLoader::loadMapByHeaders('content_tuning.csv', 'ID', 'ExpansionID');
        $questContentTuning = $this->parseBtwQuestContentTuningIds();

        $cliTaskData = $this->parseQuestV2CliTaskContentTuning();
        foreach ($cliTaskData as $questId => $ctId) {
            if (! isset($questContentTuning[$questId])) {
                $questContentTuning[$questId] = $ctId;
            }
        }

        $map = [];
        foreach ($questContentTuning as $questId => $contentTuningId) {
            if (! isset($contentTuningMap[$contentTuningId])) {
                continue;
            }

            $expansion = $contentTuningMap[$contentTuningId];
            if ($expansion < 10) {
                continue;
            }

            $map[$questId] = $expansion;
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
     * Build a zone_name (lowercase) → expansion_id map.
     *
     * @return array<string, int>
     */
    public function getZoneExpansionMap(): array
    {
        $map = [];

        $this->addQuestsJsonZones($map);
        $this->addBtwZoneNames($map);
        $this->addSupplementaryZones($map);

        return $map;
    }

    /**
     * Parse BTW addon Lua files to extract quest/achievement IDs with metadata.
     *
     * @return array{quests: list<array{id: int, expansion_id: int, zone_name: string}>, achievements: list<array{id: int, expansion_id: int, category_name: string}>}
     */
    public function parseAllAddons(): array
    {
        $questsPath = storage_path('app/blizzard/mappings/processed/quests.json');
        $achievementsPath = storage_path('app/blizzard/mappings/processed/achievements.json');

        /** @var array<int|string, array<string, mixed>> $quests */
        $quests = File::exists($questsPath) ? json_decode(File::get($questsPath), true) : [];
        /** @var array<int|string, array<string, mixed>> $achievements */
        $achievements = File::exists($achievementsPath) ? json_decode(File::get($achievementsPath), true) : [];

        return [
            'quests' => $this->normalizeQuests($quests),
            'achievements' => $this->normalizeAchievements($achievements),
        ];
    }

    /**
     * @return list<int>
     */
    public function getAllQuestIds(): array
    {
        $data = $this->parseAllAddons();

        return array_values(array_unique(array_column($data['quests'], 'id')));
    }

    /**
     * @return list<int>
     */
    public function getAllAchievementIds(): array
    {
        $data = $this->parseAllAddons();

        return array_values(array_unique(array_column($data['achievements'], 'id')));
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
     * @param  array<int|string, array<string, mixed>>  $data
     * @return list<array{id: int, expansion_id: int, zone_name: string}>
     */
    private function normalizeQuests(array $data): array
    {
        $normalized = [];

        foreach ($data as $expansionId => $expansionData) {
            if (! isset($expansionData['zones'])) {
                continue;
            }

            /** @var array<string, array{ids: list<int>}> $zones */
            $zones = $expansionData['zones'];
            foreach ($zones as $zoneName => $zoneInfo) {
                foreach ($zoneInfo['ids'] as $questId) {
                    $normalized[] = [
                        'id' => (int) $questId,
                        'expansion_id' => (int) $expansionId,
                        'zone_name' => $zoneName,
                    ];
                }
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $data
     * @return list<array{id: int, expansion_id: int, category_name: string}>
     */
    private function normalizeAchievements(array $data): array
    {
        $normalized = [];

        foreach ($data as $expansionId => $expansionData) {
            if (! isset($expansionData['categories'])) {
                continue;
            }

            /** @var array<string, array{ids: list<int>}> $categories */
            $categories = $expansionData['categories'];
            foreach ($categories as $categoryName => $categoryInfo) {
                foreach ($categoryInfo['ids'] as $achievementId) {
                    $normalized[] = [
                        'id' => (int) $achievementId,
                        'expansion_id' => (int) $expansionId,
                        'category_name' => $categoryName,
                    ];
                }
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, int>  $map
     */
    private function addQuestsJsonZones(array &$map): void
    {
        $questsPath = storage_path('app/blizzard/mappings/processed/quests.json');
        if (! File::exists($questsPath)) {
            return;
        }

        /** @var array<int|string, array{zones?: array<string, mixed>}> $data */
        $data = json_decode(File::get($questsPath), true);
        foreach ($data as $expansionId => $expansionData) {
            foreach (array_keys($expansionData['zones'] ?? []) as $zoneName) {
                $map[mb_strtolower(LuaAddonParser::normalizeApostrophes((string) $zoneName))] = (int) $expansionId;
            }
        }
    }

    /**
     * @param  array<string, int>  $map
     */
    private function addBtwZoneNames(array &$map): void
    {
        $btwMap = $this->parseBtwZoneNames();
        foreach ($btwMap as $zoneName => $expansionId) {
            $map[mb_strtolower(LuaAddonParser::normalizeApostrophes($zoneName))] = $expansionId;
        }
    }

    /**
     * @param  array<string, int>  $map
     */
    private function addSupplementaryZones(array &$map): void
    {
        /** @var array<string, int> $supplementaryZones */
        $supplementaryZones = (array) config('wow_zones', []);
        foreach ($supplementaryZones as $zoneName => $expansionId) {
            $key = mb_strtolower(LuaAddonParser::normalizeApostrophes($zoneName));
            if (! isset($map[$key])) {
                $map[$key] = $expansionId;
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function parseBtwZoneNames(): array
    {
        $btwDir = storage_path('app/blizzard/mappings/BTW');
        if (! File::isDirectory($btwDir)) {
            return [];
        }

        $map = [];

        foreach (self::BTW_EXPANSION_DIRS as $dirName => $expansionId) {
            $this->parseBtwIndexFile($btwDir, $dirName, $expansionId, $map);
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $map
     */
    private function parseBtwIndexFile(string $btwDir, string $dirName, int $expansionId, array &$map): void
    {
        $indexPaths = [
            sprintf('%s/%s/Database/Index.frFR.lua', $btwDir, $dirName),
            sprintf('%s/%s/Index.frFR.lua', $btwDir, $dirName),
        ];

        foreach ($indexPaths as $indexPath) {
            if (! File::exists($indexPath)) {
                continue;
            }

            $content = File::get($indexPath);
            preg_match_all(
                '/name\s*=\s*"([^"]+)",\s*\n\s*type\s*=\s*"category"/',
                $content,
                $matches
            );

            foreach ($matches[1] as $zoneName) {
                $map[$zoneName] = $expansionId;
            }
        }
    }

    /**
     * @return array<int, int> [quest_id => contentTuningID]
     */
    private function parseBtwQuestContentTuningIds(): array
    {
        $btwDir = storage_path('app/blizzard/mappings/BTW');
        if (! File::isDirectory($btwDir)) {
            return [];
        }

        $map = [];

        foreach (array_keys(self::BTW_EXPANSION_DIRS) as $expansionDir) {
            $this->parseBtwQuestFile($btwDir, $expansionDir, $map);
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $map
     */
    private function parseBtwQuestFile(string $btwDir, string $expansionDir, array &$map): void
    {
        $paths = [
            sprintf('%s/%s/Database/Quests.lua', $btwDir, $expansionDir),
            sprintf('%s/%s/Quests.lua', $btwDir, $expansionDir),
        ];

        foreach ($paths as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $content = File::get($path);
            preg_match_all(
                '/^\s*\[(\d+)\]\s*=\s*\{[^}]*?contentTuningID\s*=\s*(\d+)/ms',
                $content,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $questId = (int) $match[1];
                if (! isset($map[$questId])) {
                    $map[$questId] = (int) $match[2];
                }
            }
        }
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
