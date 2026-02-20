<?php

declare(strict_types=1);

namespace App\Infrastructure\Parsers;

use App\Infrastructure\Blizzard\Support\Db2CsvLoader;
use Illuminate\Support\Facades\File;

class Db2AreaExpansionMapper
{
    /**
     * Manual overrides for area_ids where DB2 data gives incorrect expansions.
     */
    private const AREA_EXPANSION_OVERRIDES = [
        // === Zones on Classic continents (EK/Kalimdor) that are post-Classic ===
        2037 => 1,    // Quel'thalas → TBC
        4706 => 3,    // Ruines de Gilnéas → Cata
        4709 => 3,    // Tarides du Sud → Cata

        // === Dungeon remakes (old map reused, quests are from remake expansion) ===
        6052 => 4,    // Salles Écarlates → MoP remake
        6066 => 4,    // Scholomance → MoP remake
        6109 => 4,    // Monastère Écarlate → MoP remake
        6298 => 4,    // Arène de Castagn'ar → MoP
        8161 => 2,    // Ulduar → WotLK (not WoD timewalking version)

        // === Legion areas with wrong DB2 resolution ===
        7969 => 6,    // Karazhan (Return to Karazhan) → Legion
        7978 => 6,    // Repos du Vigilant → Legion
        8124 => 6,    // Épée de l'Aube → Legion

        // === BfA areas on old continents ===
        4411 => 7,    // Port de Hurlevent → BfA (War Campaign hub)
        8044 => 7,    // Clairières de Tirisfal → BfA version
        8317 => 7,    // Clairières de Tirisfal → BfA
        8318 => 7,    // Clairières de Tirisfal → BfA
        8839 => 7,    // Île de Theramore → BfA
        9136 => 7,    // Rivage Bouillonnant → BfA
        9310 => 7,    // Silithus : la Plaie → BfA

        // === DF areas with wrong DB2 resolution ===
        13625 => 9,   // Répit du gardien → DF
        13769 => 9,   // Confins Interdits → DF
        13983 => 9,   // Clairières de Tirisfal (DF version) → DF

        // === TWW areas ===
        15058 => 10,  // Hautes-terres Arathies (TWW Void zone) → TWW
        15542 => 10,  // Quartier prototype Logis des PNJ → TWW

        // === Midnight areas ===
        9313 => 11,   // Lune-d'Argent → Midnight
        16092 => 11,  // Duos infâmes → Midnight
        16432 => 11,  // Hautes-terres Arathies (Midnight RPE) → Midnight
    ];

    /**
     * Build area_id → expansion_id map from DB2 data (AreaTable + Map + ContentTuning).
     *
     * @return array<int, int> [area_id => expansion_id]
     */
    public function build(): array
    {
        $areaTable = $this->parseAreaTableCsv();
        $contentTuningMap = Db2CsvLoader::loadMapByHeaders('content_tuning.csv', 'ID', 'ExpansionID');
        $mapTable = Db2CsvLoader::loadMapByHeaders('map.csv', 'ID', 'ExpansionID');

        $result = [];
        foreach (array_keys($areaTable) as $areaId) {
            if (isset(self::AREA_EXPANSION_OVERRIDES[$areaId])) {
                $result[$areaId] = self::AREA_EXPANSION_OVERRIDES[$areaId];

                continue;
            }

            $result[$areaId] = $this->resolveAreaExpansion($areaId, $areaTable, $mapTable, $contentTuningMap);
        }

        return $result;
    }

    /**
     * @param  array<int, array{continent_id: int, parent_id: int, ct_id: int}>  $areaTable
     * @param  array<int, int>  $mapTable
     * @param  array<int, int>  $contentTuningMap
     */
    private function resolveAreaExpansion(int $areaId, array $areaTable, array $mapTable, array $contentTuningMap): int
    {
        if (! isset($areaTable[$areaId])) {
            return 0;
        }

        $area = $areaTable[$areaId];
        $mapExp = $mapTable[$area['continent_id']] ?? -1;
        $ctExp = ($area['ct_id'] > 0 && isset($contentTuningMap[$area['ct_id']]))
            ? $contentTuningMap[$area['ct_id']]
            : -99;

        if ($mapExp > 0) {
            return $mapExp;
        }

        if ($mapExp === 0) {
            return max($ctExp, 0);
        }

        if ($area['parent_id'] > 0) {
            return $this->resolveAreaExpansion($area['parent_id'], $areaTable, $mapTable, $contentTuningMap);
        }

        return 0;
    }

    /**
     * Parse AreaTable CSV → area_id → {continent_id, parent_id, ct_id}.
     *
     * @return array<int, array{continent_id: int, parent_id: int, ct_id: int}>
     */
    private function parseAreaTableCsv(): array
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
        $contIdx = (int) array_search('ContinentID', $headers, true);
        $parentIdx = (int) array_search('ParentAreaID', $headers, true);
        $ctIdx = (int) array_search('ContentTuningID', $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $map[(int) $row[$idIdx]] = [
                'continent_id' => (int) $row[$contIdx],
                'parent_id' => (int) $row[$parentIdx],
                'ct_id' => (int) $row[$ctIdx],
            ];
        }

        fclose($handle);

        return $map;
    }
}
