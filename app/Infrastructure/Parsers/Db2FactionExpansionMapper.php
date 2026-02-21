<?php

declare(strict_types=1);

namespace App\Infrastructure\Parsers;

use Illuminate\Support\Facades\File;

class Db2FactionExpansionMapper
{
    /**
     * Parent faction IDs that serve as expansion headers in the hierarchy.
     */
    private const EXPANSION_PARENT_FACTIONS = [
        1118 => 0,  // Classique
        980 => 1,   // The Burning Crusade
        1097 => 2,  // Wrath of the Lich King
        1162 => 3,  // Cataclysm
        1245 => 4,  // Mists of Pandaria
        1444 => 5,  // Warlords of Draenor
        1834 => 6,  // Legion
        2104 => 7,  // Battle for Azeroth
        2414 => 8,  // Shadowlands
        2506 => 9,  // Dragonflight
        2569 => 10, // The War Within
        2698 => 11, // Midnight
    ];

    /**
     * Build faction_id → expansion_id map from faction.csv parent hierarchy.
     *
     * @return array<int, int>
     */
    public function build(): array
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
        $nameIdx = (int) array_search('Name_lang', $headers, true);
        $repIdx = (int) array_search('ReputationIndex', $headers, true);
        $parentIdx = (int) array_search('ParentFactionID', $headers, true);

        /** @var array<int, int> $parentMap */
        $parentMap = [];
        /** @var list<int> $reputationFactionIds */
        $reputationFactionIds = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $factionId = (int) $row[$idIdx];
            $parentId = (int) $row[$parentIdx];
            $repIndex = (int) $row[$repIdx];
            $name = (string) $row[$nameIdx];

            $parentMap[$factionId] = $parentId;

            if ($repIndex < 0) {
                continue;
            }

            if ($parentId === 0) {
                continue;
            }

            if ($this->shouldExclude($name)) {
                continue;
            }

            $reputationFactionIds[] = $factionId;
        }

        fclose($handle);

        $result = [];
        foreach ($reputationFactionIds as $reputationFactionId) {
            $expansion = $this->resolveExpansion($reputationFactionId, $parentMap);
            if ($expansion !== null) {
                $result[$reputationFactionId] = $expansion;
            }
        }

        return $result;
    }

    /**
     * Walk the parent chain to find the expansion header.
     *
     * @param  array<int, int>  $parentMap
     */
    private function resolveExpansion(int $factionId, array $parentMap): ?int
    {
        $current = $factionId;

        for ($i = 0; $i < 10; $i++) {
            $parentId = $parentMap[$current] ?? 0;

            if ($parentId === 0) {
                return null;
            }

            if (isset(self::EXPANSION_PARENT_FACTIONS[$parentId])) {
                return self::EXPANSION_PARENT_FACTIONS[$parentId];
            }

            $current = $parentId;
        }

        return null;
    }

    /**
     * Build faction_id → max_renown_level map for renown factions.
     *
     * Uses faction.csv RenownCurrencyID joined with currency_types.csv MaxQty.
     *
     * @return array<int, int>
     */
    public function buildMaxRenownMap(): array
    {
        $factionCsvPath = storage_path('app/blizzard/faction.csv');
        $currencyCsvPath = storage_path('app/blizzard/currency_types.csv');

        if (! File::exists($factionCsvPath) || ! File::exists($currencyCsvPath)) {
            return [];
        }

        $renownCurrencyMap = $this->parseRenownCurrencyIds($factionCsvPath);

        if ($renownCurrencyMap === []) {
            return [];
        }

        $currencyMaxQty = $this->parseCurrencyMaxQty($currencyCsvPath);

        $result = [];
        foreach ($renownCurrencyMap as $factionId => $currencyId) {
            if (isset($currencyMaxQty[$currencyId])) {
                $result[$factionId] = $currencyMaxQty[$currencyId];
            }
        }

        return $result;
    }

    /**
     * Parse faction.csv to extract faction_id → RenownCurrencyID for renown factions.
     *
     * @return array<int, int>
     */
    private function parseRenownCurrencyIds(string $csvPath): array
    {
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
        $renownCurrencyIdx = (int) array_search('RenownCurrencyID', $headers, true);

        $map = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $currencyId = (int) $row[$renownCurrencyIdx];
            if ($currencyId > 0) {
                $map[(int) $row[$idIdx]] = $currencyId;
            }
        }

        fclose($handle);

        return $map;
    }

    /**
     * Parse currency_types.csv to extract currency_id → MaxQty.
     *
     * @return array<int, int>
     */
    private function parseCurrencyMaxQty(string $csvPath): array
    {
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
        $maxQtyIdx = (int) array_search('MaxQty', $headers, true);

        $map = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $maxQty = (int) $row[$maxQtyIdx];
            if ($maxQty > 0) {
                $map[(int) $row[$idIdx]] = $maxQty;
            }
        }

        fclose($handle);

        return $map;
    }

    private function shouldExclude(string $name): bool
    {
        if (str_contains($name, '(parangon)')) {
            return true;
        }

        return str_contains($name, 'DEPRECATED') || str_contains($name, '[DNT]') || str_contains($name, 'JOUEUR');
    }
}
