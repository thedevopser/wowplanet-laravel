<?php

declare(strict_types=1);

namespace App\Infrastructure\Parsers;

use App\Infrastructure\Blizzard\ExpansionTierMatcher;
use App\Infrastructure\Blizzard\Support\Db2CsvLoader;
use Illuminate\Support\Facades\File;

class Db2AchievementExpansionMapper
{
    /**
     * Manual overrides for achievement_ids where DB2 hierarchy gives incorrect expansions.
     *
     * @var array<int, int>
     */
    private const ACHIEVEMENT_EXPANSION_OVERRIDES = [];

    /**
     * Direct category_id → expansion_id mapping for categories
     * whose names don't contain expansion keywords but whose expansion is known.
     *
     * @var array<int, int>
     */
    private const CATEGORY_EXPANSION_MAP = [
        // Contenu d'extension (15301) subcategories
        14941 => 2,  // Tournoi d'Argent → WotLK
        15075 => 3,  // Tol Barad → Cataclysm
        15222 => 8,  // Ordalie (Torghast) → Shadowlands
        15302 => 4,  // Scénarios de Mists of Pandaria → MoP
        15303 => 5,  // Fief de Draenor → WoD
        15304 => 6,  // Domaines de classe de Legion → Legion
        15307 => 7,  // Exploration des îles → BfA
        15308 => 7,  // Effort de guerre → BfA
        15417 => 7,  // Cœur d'Azeroth → BfA
        15426 => 7,  // Visions de N'Zoth → BfA
        15440 => 8,  // Tourment → Shadowlands
        15441 => 8,  // Sanctums des congrégations → Shadowlands
        15462 => 9,  // Vol dynamique → Dragonflight (fallback for old-world races)
        15546 => 7,  // Visions de N'Zoth redécouvertes → BfA
        15552 => 10, // Chroniques → TWW
        15605 => 11, // Traque → Midnight

        // Gouffres
        15522 => 10, // Gouffres (root) → TWW

        // Logis
        15606 => 10, // Logis (root) → TWW

        // Évènements mondiaux
        15545 => 10, // Duos infâmes → TWW
        15567 => 11, // Les Bastonneurs → Midnight

        // Statistiques subcategories
        15533 => 10, // Gouffres (stats) → TWW
        15550 => 10, // Duos infâmes (stats) → TWW
        15562 => 6,  // Legion Remix (stats) → Legion

        // Champs de bataille (sous catégorie root 95: PvP)
        14801 => 0,  // Vallée d'Alterac → Classic
        14802 => 0,  // Bassin Arathi → Classic
        14803 => 1,  // Œil du cyclone → TBC
        14804 => 0,  // Goulet des Chanteguerres → Classic
        14901 => 2,  // Joug-d'hiver → WotLK
        15003 => 2,  // L'île des Conquérants → WotLK
        15073 => 3,  // Bataille de Gilnéas → Cata
        15074 => 3,  // Pics-Jumeaux → Cata
        15162 => 4,  // Mines d'Éclargent → MoP
        15163 => 4,  // Temple de Kotmogu → MoP
        15218 => 5,  // Gorge de Vent-Caverneux → WoD
        15292 => 7,  // Rivage Bouillonnant → BfA
        15414 => 5,  // A'shran → WoD
        15525 => 10, // Ravin d'Abondabîme → TWW
        15575 => 11, // Terrain d'entraînement → Midnight

        // Événements mondiaux - holidays (ajoutés avec le système de HF en WotLK 3.0)
        156 => 2,    // Voile d'hiver → WotLK
        158 => 2,    // Sanssaint → WotLK
        159 => 2,    // Jardin des nobles → WotLK
        160 => 2,    // Fête lunaire → WotLK
        161 => 2,    // Solstice d'été → WotLK
        162 => 2,    // Fête des Brasseurs → WotLK
        163 => 2,    // Semaine des enfants → WotLK
        187 => 2,    // De l'amour dans l'air → WotLK
        14981 => 2,  // Bienfaits du pèlerin → WotLK
        15101 => 3,  // Foire de Sombrelune → Cata (revamp 4.3)
        15454 => 5,  // Marcheurs du temps → WoD
        15532 => 0,  // Célébration d'anniversaire → Classic

        // Guilde (système ajouté en Cata)
        15088 => 3,  // Guilde: Général → Cata
    ];

    /**
     * Categories spanning multiple expansions where area-name matching
     * uses only modern zones (>= DF) to avoid misattributing old-world content.
     *
     * @var array<int, true>
     */
    private const MULTI_EXPANSION_CATEGORIES = [
        15462 => true, // Vol dynamique (DF + TWW + Midnight)
    ];

    private const AREA_MATCH_MIN_EXPANSION = 9;

    private const AREA_NAME_MIN_LENGTH = 5;

    /**
     * Area names that are common French words causing false positives in text matching.
     *
     * @var array<string, true>
     */
    private const AREA_NAME_BLACKLIST = [
        'ambassade' => true,
        'temple' => true,
        'caverne' => true,
        'bastion' => true,
        'refuge' => true,
        'repaire' => true,
        'atelier' => true,
        'prison' => true,
        'marché' => true,
        'cimetière' => true,
        'ferme' => true,
        'grotte' => true,
        'ruines' => true,
        'passage' => true,
        'galerie' => true,
        'quartier' => true,
        'sanctuaire' => true,
        'avant-poste' => true,
    ];

    /**
     * Build achievement_id → expansion_id map from DB2 data.
     *
     * Resolution order:
     * 1. Manual overrides
     * 2. Instance_ID → Map.ExpansionID (dungeon/raid achievements)
     * 3. For multi-expansion categories: area-name matching (modern zones only)
     * 4. Category hierarchy → CATEGORY_EXPANSION_MAP + ExpansionTierMatcher
     * 5. ExpansionTierMatcher on title + description (all achievements)
     * 6. Area-name matching on title + description (all zones)
     * 7. CriteriaTree description-based resolution (keywords + area names)
     * 8. Supercedes chain propagation (post-pass)
     * 9. Unresolved → NOT included
     *
     * @return array<int, int> [achievement_id => expansion_id]
     */
    public function build(): array
    {
        $categories = $this->parseCategoryCsv();
        $achievements = $this->parseAchievementCsv();
        $mapTable = Db2CsvLoader::loadMapByHeaders('map.csv', 'ID', 'ExpansionID');
        $modernAreaLookup = $this->buildAreaNameLookup($mapTable, self::AREA_MATCH_MIN_EXPANSION);
        $allAreaLookup = $this->buildAreaNameLookup($mapTable, 0);
        $criteriaTree = $this->parseCriteriaTreeIndex();

        $result = [];
        foreach ($achievements as $achievementId => $data) {
            if (isset(self::ACHIEVEMENT_EXPANSION_OVERRIDES[$achievementId])) { // @phpstan-ignore isset.offset
                $result[$achievementId] = self::ACHIEVEMENT_EXPANSION_OVERRIDES[$achievementId];

                continue;
            }

            // Instance-based resolution (dungeons/raids)
            if ($data['instance_id'] > 0 && isset($mapTable[$data['instance_id']])) {
                $result[$achievementId] = $mapTable[$data['instance_id']];

                continue;
            }

            // For multi-expansion categories, try modern area-name matching first
            if ($this->isMultiExpansionCategory($data['category'], $categories)) {
                $expansion = $this->resolveExpansionFromText(
                    $data['title'],
                    $data['description'],
                    $modernAreaLookup,
                );

                if ($expansion !== null) {
                    $result[$achievementId] = $expansion;

                    continue;
                }
            }

            // Category hierarchy resolution (CATEGORY_EXPANSION_MAP + keyword matching)
            $expansion = $this->resolveExpansionFromCategory(
                $data['category'],
                $categories,
            );

            if ($expansion !== null) {
                $result[$achievementId] = $expansion;

                continue;
            }

            // ExpansionTierMatcher on title + description (continent-level keywords)
            $expansion = $this->resolveExpansionFromTextKeywords(
                $data['title'],
                $data['description'],
            );

            if ($expansion !== null) {
                $result[$achievementId] = $expansion;

                continue;
            }

            // Area-name matching on title + description (all zones, zone-level names)
            $expansion = $this->resolveExpansionFromText(
                $data['title'],
                $data['description'],
                $allAreaLookup,
            );

            if ($expansion !== null) {
                $result[$achievementId] = $expansion;

                continue;
            }

            // CriteriaTree description-based resolution
            $expansion = $this->resolveExpansionFromCriteriaTree(
                $data['criteria_tree'],
                $criteriaTree,
                $allAreaLookup,
            );

            if ($expansion !== null) {
                $result[$achievementId] = $expansion;
            }
        }

        // Post-pass: propagate via Supercedes chain
        foreach ($achievements as $achievementId => $data) {
            if (isset($result[$achievementId])) {
                continue;
            }

            $supersedesId = $data['supercedes'];
            $visited = [];
            while ($supersedesId > 0 && ! isset($visited[$supersedesId])) {
                $visited[$supersedesId] = true;
                if (isset($result[$supersedesId])) {
                    $result[$achievementId] = $result[$supersedesId];

                    break;
                }

                $supersedesId = $achievements[$supersedesId]['supercedes'] ?? 0;
            }
        }

        return $result;
    }

    /**
     * Check if achievement belongs to a multi-expansion category.
     *
     * @param  array<int, array{name: string, parent: int}>  $categories
     */
    private function isMultiExpansionCategory(int $categoryId, array $categories): bool
    {
        $visited = [];

        while ($categoryId > 0 && ! isset($visited[$categoryId])) {
            $visited[$categoryId] = true;

            if (isset(self::MULTI_EXPANSION_CATEGORIES[$categoryId])) {
                return true;
            }

            if (! isset($categories[$categoryId])) {
                break;
            }

            $categoryId = $categories[$categoryId]['parent'];
        }

        return false;
    }

    /**
     * Build area_name → expansion_id lookup from area_table.csv + map.csv.
     * Sorted by name length descending (most specific matches first).
     *
     * @param  array<int, int>  $mapTable  [map_id => expansion_id]
     * @return array<string, int> [lowercase_area_name => expansion_id]
     */
    private function buildAreaNameLookup(array $mapTable, int $minExpansion): array
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

        $nameIdx = (int) array_search('AreaName_lang', $headers, true);
        $contIdx = (int) array_search('ContinentID', $headers, true);

        $lookup = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $name = trim((string) $row[$nameIdx]);
            $continentId = (int) $row[$contIdx];
            if ($name === '') {
                continue;
            }

            if ($name === '-') {
                continue;
            }

            if (mb_strlen($name) < self::AREA_NAME_MIN_LENGTH) {
                continue;
            }

            if (! isset($mapTable[$continentId])) {
                continue;
            }

            $expansion = $mapTable[$continentId];
            if ($expansion < $minExpansion) {
                continue;
            }

            // Skip dev/test areas
            if (mb_stripos($name, 'dev ') !== false) {
                continue;
            }

            if (mb_stripos($name, 'test') !== false) {
                continue;
            }

            $lower = mb_strtolower(LuaAddonParser::normalizeApostrophes($name));

            // Skip generic area names that cause false positives
            if (isset(self::AREA_NAME_BLACKLIST[$lower])) {
                continue;
            }

            // Keep the entry with the highest expansion if duplicates
            if (! isset($lookup[$lower]) || $lookup[$lower] < $expansion) {
                $lookup[$lower] = $expansion;
            }
        }

        fclose($handle);

        // Sort by name length descending (most specific first)
        uksort($lookup, static fn (string $a, string $b): int => mb_strlen($b) - mb_strlen($a));

        return $lookup;
    }

    /**
     * Try to resolve expansion using ExpansionTierMatcher on title + description.
     * Catches continent-level keywords (Norfendre, Outreterre, Pandarie, etc.).
     */
    private function resolveExpansionFromTextKeywords(string $title, string $description): ?int
    {
        $text = LuaAddonParser::normalizeApostrophes($description.' '.$title);

        return ExpansionTierMatcher::match($text);
    }

    /**
     * Try to resolve expansion from achievement title/description by matching
     * against area names from area_table.csv.
     *
     * @param  array<string, int>  $areaNameLookup  [lowercase_area_name => expansion_id]
     */
    private function resolveExpansionFromText(string $title, string $description, array $areaNameLookup): ?int
    {
        if ($areaNameLookup === []) {
            return null;
        }

        $text = mb_strtolower(LuaAddonParser::normalizeApostrophes($description.' '.$title));

        foreach ($areaNameLookup as $areaName => $expansion) {
            if (mb_strpos($text, $areaName) !== false) {
                return $expansion;
            }
        }

        return null;
    }

    /**
     * Walk up the category hierarchy checking CATEGORY_EXPANSION_MAP
     * then ExpansionTierMatcher at each level.
     *
     * @param  array<int, array{name: string, parent: int}>  $categories
     */
    private function resolveExpansionFromCategory(int $categoryId, array $categories): ?int
    {
        $visited = [];

        while ($categoryId > 0 && ! isset($visited[$categoryId])) {
            $visited[$categoryId] = true;

            if (isset(self::CATEGORY_EXPANSION_MAP[$categoryId])) {
                return self::CATEGORY_EXPANSION_MAP[$categoryId];
            }

            if (! isset($categories[$categoryId])) {
                break;
            }

            $matched = ExpansionTierMatcher::match($categories[$categoryId]['name']);
            if ($matched !== null) {
                return $matched;
            }

            $categoryId = $categories[$categoryId]['parent'];
        }

        return null;
    }

    /**
     * @return array<int, array{name: string, parent: int}>
     */
    private function parseCategoryCsv(): array
    {
        $csvPath = storage_path('app/blizzard/achievement_category.csv');
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
        $parentIdx = (int) array_search('Parent', $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $map[(int) $row[$idIdx]] = [
                'name' => (string) $row[$nameIdx],
                'parent' => (int) $row[$parentIdx],
            ];
        }

        fclose($handle);

        return $map;
    }

    /**
     * @return array<int, array{category: int, instance_id: int, title: string, description: string, criteria_tree: int, supercedes: int}>
     */
    private function parseAchievementCsv(): array
    {
        $csvPath = storage_path('app/blizzard/achievement.csv');
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
        $categoryIdx = (int) array_search('Category', $headers, true);
        $instanceIdx = (int) array_search('Instance_ID', $headers, true);
        $titleIdx = (int) array_search('Title_lang', $headers, true);
        $descIdx = (int) array_search('Description_lang', $headers, true);
        $criteriaTreeIdx = (int) array_search('Criteria_tree', $headers, true);
        $supersedesIdx = (int) array_search('Supercedes', $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $map[(int) $row[$idIdx]] = [
                'category' => (int) $row[$categoryIdx],
                'instance_id' => (int) $row[$instanceIdx],
                'title' => $row[$titleIdx] ?? '',
                'description' => $row[$descIdx] ?? '',
                'criteria_tree' => (int) ($row[$criteriaTreeIdx] ?? 0),
                'supercedes' => (int) ($row[$supersedesIdx] ?? 0),
            ];
        }

        fclose($handle);

        return $map;
    }

    /**
     * Parse criteria_tree.csv into a flat indexed structure with reverse parent→children index.
     *
     * @return array<int, array{description: string, children: list<int>}>
     */
    private function parseCriteriaTreeIndex(): array
    {
        $csvPath = storage_path('app/blizzard/criteria_tree.csv');
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
        $descIdx = (int) array_search('Description_lang', $headers, true);
        $parentIdx = (int) array_search('Parent', $headers, true);

        $nodes = [];
        $parentMap = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $id = (int) $row[$idIdx];
            $parent = (int) $row[$parentIdx];
            $nodes[$id] = [
                'description' => $row[$descIdx] ?? '',
                'children' => [],
            ];
            if ($parent > 0) {
                $parentMap[$id] = $parent;
            }
        }

        fclose($handle);

        // Build reverse parent→children index
        foreach ($parentMap as $childId => $parentId) {
            if (isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = $childId;
            }
        }

        return $nodes;
    }

    /**
     * Collect all descendant descriptions from a criteria_tree root node via BFS.
     *
     * @param  array<int, array{description: string, children: list<int>}>  $tree
     */
    private function collectCriteriaDescriptions(int $rootId, array $tree): string
    {
        if (! isset($tree[$rootId])) {
            return '';
        }

        $descriptions = [];
        $queue = [$rootId];
        $visited = [];

        while ($queue !== []) {
            $nodeId = array_shift($queue);
            if (isset($visited[$nodeId])) {
                continue;
            }

            $visited[$nodeId] = true;

            $desc = $tree[$nodeId]['description'] ?? '';
            if ($desc !== '') {
                $descriptions[] = $desc;
            }

            foreach ($tree[$nodeId]['children'] as $childId) {
                $queue[] = $childId;
            }
        }

        return implode(' ', $descriptions);
    }

    /**
     * Try to resolve expansion from criteria tree descriptions.
     *
     * @param  array<int, array{description: string, children: list<int>}>  $criteriaTree
     * @param  array<string, int>  $areaNameLookup
     */
    private function resolveExpansionFromCriteriaTree(
        int $criteriaTreeId,
        array $criteriaTree,
        array $areaNameLookup,
    ): ?int {
        if ($criteriaTreeId <= 0 || $criteriaTree === []) {
            return null;
        }

        $text = $this->collectCriteriaDescriptions($criteriaTreeId, $criteriaTree);
        if ($text === '') {
            return null;
        }

        // Try keyword matching first (cheaper)
        $expansion = $this->resolveExpansionFromTextKeywords('', $text);
        if ($expansion !== null) {
            return $expansion;
        }

        // Then area-name matching
        return $this->resolveExpansionFromText('', $text, $areaNameLookup);
    }
}
