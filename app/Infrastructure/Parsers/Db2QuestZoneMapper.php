<?php

declare(strict_types=1);

namespace App\Infrastructure\Parsers;

use Illuminate\Support\Facades\File;

class Db2QuestZoneMapper
{
    /**
     * Build quest_id → zone_name map from QuestPOIBlob + UiMap CSVs.
     *
     * @return array<int, string>
     */
    public static function build(): array
    {
        $uiMaps = self::parseUiMapCsv();
        if ($uiMaps === []) {
            return [];
        }

        $questUiMaps = self::parseQuestPoiBlobCsv();
        if ($questUiMaps === []) {
            return [];
        }

        $map = [];
        foreach ($questUiMaps as $questId => $uiMapId) {
            $zoneName = self::walkToZone($uiMapId, $uiMaps);
            if ($zoneName !== null && $zoneName !== '') {
                $map[$questId] = $zoneName;
            }
        }

        return $map;
    }

    /**
     * Parse UiMap.csv for map hierarchy.
     *
     * @return array<int, array{name: string, type: int, parent: int}>
     */
    private static function parseUiMapCsv(): array
    {
        $csvPath = storage_path('app/blizzard/ui_map.csv');
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
        $nameIdx = (int) array_search('Name_lang', $headers, true);
        $typeIdx = (int) array_search('Type', $headers, true);
        $parentIdx = (int) array_search('ParentUiMapID', $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $map[(int) $row[$idIdx]] = [
                'name' => trim($row[$nameIdx] ?? ''),
                'type' => (int) ($row[$typeIdx] ?? 0),
                'parent' => (int) ($row[$parentIdx] ?? 0),
            ];
        }

        fclose($handle);

        return $map;
    }

    /**
     * Parse QuestPOIBlob.csv to get best UiMapID per quest.
     * Prefers entries with ObjectiveIndex = -1 (quest-level POI).
     *
     * @return array<int, int> [questId => uiMapId]
     */
    private static function parseQuestPoiBlobCsv(): array
    {
        $csvPath = storage_path('app/blizzard/quest_poi_blob.csv');
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

        $questIdIdx = (int) array_search('QuestID', $headers, true);
        $uiMapIdIdx = (int) array_search('UiMapID', $headers, true);
        $objIdx = (int) array_search('ObjectiveIndex', $headers, true);

        $map = [];
        $hasQuestLevel = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $questId = (int) $row[$questIdIdx];
            $uiMapId = (int) $row[$uiMapIdIdx];
            $objectiveIndex = (int) $row[$objIdx];

            if ($uiMapId <= 0) {
                continue;
            }

            // Prefer ObjectiveIndex = -1 (quest-level POI)
            if ($objectiveIndex === -1) {
                $map[$questId] = $uiMapId;
                $hasQuestLevel[$questId] = true;
            } elseif (! isset($map[$questId]) || ! ($hasQuestLevel[$questId] ?? false)) {
                $map[$questId] = $uiMapId;
            }
        }

        fclose($handle);

        return $map;
    }

    /**
     * Walk up UiMap hierarchy to find a zone-level map name.
     * Stops at Type 3 (Zone) or Type 2 (Continent) as fallback.
     *
     * @param  array<int, array{name: string, type: int, parent: int}>  $uiMaps
     */
    private static function walkToZone(int $uiMapId, array $uiMaps): ?string
    {
        $visited = [];

        while ($uiMapId > 0 && ! isset($visited[$uiMapId])) {
            $visited[$uiMapId] = true;

            if (! isset($uiMaps[$uiMapId])) {
                return null;
            }

            $entry = $uiMaps[$uiMapId];

            // Type 3 = Zone (ideal target)
            if ($entry['type'] === 3) {
                return $entry['name'];
            }

            // Type 2 = Continent (fallback — don't go higher)
            if ($entry['type'] === 2) {
                return $entry['name'];
            }

            $uiMapId = $entry['parent'];
        }

        return null;
    }
}
