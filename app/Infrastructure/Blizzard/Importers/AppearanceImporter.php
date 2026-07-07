<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Parsers\SimpleArmoryParser;
use App\Models\WowAppearance;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

final class AppearanceImporter
{
    /**
     * Mapping InventoryType (DB2) → [slot, category]. Seuls ces types sont transmoggables ;
     * les autres (cou, doigt, bijou, sac, munition…) ne sont pas collectionnables.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const INVENTORY_TYPE_SLOTS = [
        1 => ['HEAD', 'Armure'],
        3 => ['SHOULDER', 'Armure'],
        4 => ['SHIRT', 'Armure'],
        5 => ['CHEST', 'Armure'],
        6 => ['WAIST', 'Armure'],
        7 => ['LEGS', 'Armure'],
        8 => ['FEET', 'Armure'],
        9 => ['WRIST', 'Armure'],
        10 => ['HAND', 'Armure'],
        16 => ['CLOAK', 'Armure'],
        19 => ['TABARD', 'Armure'],
        20 => ['CHEST', 'Armure'], // robe → poitrine
        13 => ['WEAPON', 'Arme'],
        14 => ['SHIELD', 'Arme'],
        15 => ['RANGED', 'Arme'],
        17 => ['TWOHWEAPON', 'Arme'],
        21 => ['WEAPON', 'Arme'], // main hand
        22 => ['WEAPONOFFHAND', 'Arme'],
        23 => ['HOLDABLE', 'Arme'],
        25 => ['RANGED', 'Arme'], // thrown
        26 => ['RANGED', 'Arme'], // ranged right
        28 => ['HOLDABLE', 'Arme'], // relic
    ];

    public function import(): void
    {
        $iconMap = $this->loadIconMap();
        if ($iconMap === []) {
            return;
        }

        $appearanceItems = $this->loadAppearanceItems();
        $itemMap = $this->loadItemData($appearanceItems);
        $iconNames = $this->loadIconNames($iconMap);

        $rows = $this->buildRows($iconMap, $appearanceItems, $itemMap, $iconNames);
        $this->saveRows($rows);
    }

    /**
     * item_appearance.csv → [appearanceId => iconFileDataId].
     *
     * @return array<int, int>
     */
    private function loadIconMap(): array
    {
        $this->info('Loading item_appearance.csv...');

        $path = storage_path('app/blizzard/item_appearance.csv');
        if (! File::exists($path)) {
            $this->info('  WARNING: item_appearance.csv not found.');

            return [];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $iconIdx = (int) array_search('DefaultIconFileDataID', $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $id = (int) ($row[$idIdx] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $map[$id] = (int) ($row[$iconIdx] ?? 0);
        }

        fclose($handle);

        $this->info(sprintf('  Found %d appearances.', count($map)));

        return $map;
    }

    /**
     * item_modified_appearance.csv → [appearanceId => list<itemId>].
     *
     * @return array<int, list<int>>
     */
    private function loadAppearanceItems(): array
    {
        $this->info('Loading item_modified_appearance.csv...');

        $path = storage_path('app/blizzard/item_modified_appearance.csv');
        if (! File::exists($path)) {
            $this->info('  WARNING: item_modified_appearance.csv not found.');

            return [];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $appIdx = (int) array_search('ItemAppearanceID', $headers, true);
        $itemIdx = (int) array_search('ItemID', $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $appearanceId = (int) ($row[$appIdx] ?? 0);
            $itemId = (int) ($row[$itemIdx] ?? 0);
            if ($appearanceId <= 0) {
                continue;
            }

            if ($itemId <= 0) {
                continue;
            }

            $map[$appearanceId][] = $itemId;
        }

        fclose($handle);

        return $map;
    }

    /**
     * item_sparse.csv → [itemId => [name, expansion, inventoryType, quality]], filtré sur les items référencés.
     *
     * @param  array<int, list<int>>  $appearanceItems
     * @return array<int, array{name: string, expansion: int, inventory_type: int, quality: int}>
     */
    private function loadItemData(array $appearanceItems): array
    {
        $this->info('Loading item_sparse.csv...');

        $needed = [];
        foreach ($appearanceItems as $appearanceItem) {
            foreach ($appearanceItem as $itemId) {
                $needed[$itemId] = true;
            }
        }

        $path = storage_path('app/blizzard/item_sparse.csv');
        if (! File::exists($path)) {
            $this->info('  WARNING: item_sparse.csv not found.');

            return [];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $nameIdx = (int) array_search('Display_lang', $headers, true);
        $expIdx = (int) array_search('ExpansionID', $headers, true);
        $invIdx = (int) array_search('InventoryType', $headers, true);
        $qualIdx = (int) array_search('OverallQualityID', $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $id = (int) ($row[$idIdx] ?? 0);
            if ($id <= 0) {
                continue;
            }

            if (! isset($needed[$id])) {
                continue;
            }

            $name = trim($row[$nameIdx] ?? '');
            if ($name === '') {
                continue;
            }

            if ($this->isPlaceholderName($name)) {
                continue;
            }

            $map[$id] = [
                'name' => $name,
                'expansion' => (int) ($row[$expIdx] ?? 0),
                'inventory_type' => (int) ($row[$invIdx] ?? 0),
                'quality' => (int) ($row[$qualIdx] ?? 0),
            ];
        }

        fclose($handle);

        $this->info(sprintf('  Found %d referenced items.', count($map)));

        return $map;
    }

    /**
     * manifest_interface_data.csv → [fileDataId => icon_name] pour les FileDataID d'icônes utilisés.
     * Reconstruit une icône zamimg exploitable à partir du FileDataID numérique.
     *
     * @param  array<int, int>  $iconMap  [appearanceId => fileDataId]
     * @return array<int, string>
     */
    private function loadIconNames(array $iconMap): array
    {
        $this->info('Loading manifest_interface_data.csv (icon names)...');

        $needed = array_flip(array_filter(array_values($iconMap), fn (int $fdid): bool => $fdid > 0));
        if ($needed === []) {
            return [];
        }

        $path = storage_path('app/blizzard/manifest_interface_data.csv');
        if (! File::exists($path)) {
            $this->info('  WARNING: manifest_interface_data.csv not found.');

            return [];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $fileNameIdx = (int) array_search('FileName', $headers, true);

        $names = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $id = (int) ($row[$idIdx] ?? 0);
            if ($id <= 0) {
                continue;
            }

            if (! isset($needed[$id])) {
                continue;
            }

            // "INV_Chest_Samurai.blp" → "inv_chest_samurai"
            $fileName = trim($row[$fileNameIdx] ?? '');
            if ($fileName === '') {
                continue;
            }

            $names[$id] = mb_strtolower(pathinfo($fileName, PATHINFO_FILENAME));
        }

        fclose($handle);

        $this->info(sprintf('  Resolved %d icon names.', count($names)));

        return $names;
    }

    /**
     * @param  array<int, int>  $iconMap
     * @param  array<int, list<int>>  $appearanceItems
     * @param  array<int, array{name: string, expansion: int, inventory_type: int, quality: int}>  $itemMap
     * @param  array<int, string>  $iconNames
     * @return list<array{id: int, name_fr: string, slot: string|null, category: string|null, quality: int|null, item_id: int|null, icon_file_data_id: int|null, icon_url: string|null, expansion_id: int|null, source: string|null, is_active: bool}>
     */
    private function buildRows(array $iconMap, array $appearanceItems, array $itemMap, array $iconNames = []): array
    {
        $rows = [];
        $active = 0;

        foreach ($iconMap as $appearanceId => $iconFdid) {
            $itemIds = $appearanceItems[$appearanceId] ?? [];
            $representative = $this->pickRepresentative($itemIds, $itemMap);

            $iconName = $iconNames[$iconFdid] ?? null;

            $rows[] = [
                'id' => $appearanceId,
                'name_fr' => $representative['name'] ?? sprintf('[EN] Appearance #%d', $appearanceId),
                'slot' => $representative['slot'],
                'category' => $representative['category'],
                'quality' => $representative['quality'],
                'item_id' => $representative['item_id'],
                'icon_file_data_id' => $iconFdid > 0 ? $iconFdid : null,
                'icon_url' => $iconName !== null ? SimpleArmoryParser::buildIconUrl($iconName) : null,
                'expansion_id' => $representative['expansion'],
                'source' => null,
                'is_active' => $representative['is_active'],
            ];

            if ($representative['is_active']) {
                $active++;
            }
        }

        $this->info(sprintf('  Built %d rows (%d collectible).', count($rows), $active));

        return $rows;
    }

    /**
     * Choisit l'item représentatif d'une apparence : meilleure qualité parmi les items nommés,
     * en privilégiant un slot transmoggable.
     *
     * @param  list<int>  $itemIds
     * @param  array<int, array{name: string, expansion: int, inventory_type: int, quality: int}>  $itemMap
     * @return array{name: string|null, slot: string|null, category: string|null, quality: int|null, item_id: int|null, expansion: int|null, is_active: bool}
     */
    private function pickRepresentative(array $itemIds, array $itemMap): array
    {
        $transmoggable = null;
        $fallback = null;

        foreach ($itemIds as $itemId) {
            if (! isset($itemMap[$itemId])) {
                continue;
            }

            $item = $itemMap[$itemId] + ['item_id' => $itemId];
            $isTransmoggable = isset(self::INVENTORY_TYPE_SLOTS[$item['inventory_type']]);

            if ($isTransmoggable) {
                if ($transmoggable === null || $item['quality'] > $transmoggable['quality']) {
                    $transmoggable = $item;
                }
            } elseif ($fallback === null || $item['quality'] > $fallback['quality']) {
                $fallback = $item;
            }
        }

        if ($transmoggable !== null) {
            [$slot, $category] = self::INVENTORY_TYPE_SLOTS[$transmoggable['inventory_type']];

            return [
                'name' => $transmoggable['name'],
                'slot' => $slot,
                'category' => $category,
                'quality' => $transmoggable['quality'],
                'item_id' => $transmoggable['item_id'],
                'expansion' => $transmoggable['expansion'],
                'is_active' => true,
            ];
        }

        if ($fallback !== null) {
            return [
                'name' => $fallback['name'],
                'slot' => null,
                'category' => null,
                'quality' => $fallback['quality'],
                'item_id' => $fallback['item_id'],
                'expansion' => $fallback['expansion'],
                'is_active' => false,
            ];
        }

        return [
            'name' => null,
            'slot' => null,
            'category' => null,
            'quality' => null,
            'item_id' => null,
            'expansion' => null,
            'is_active' => false,
        ];
    }

    /**
     * @param  list<array{id: int, name_fr: string, slot: string|null, category: string|null, quality: int|null, item_id: int|null, icon_file_data_id: int|null, icon_url: string|null, expansion_id: int|null, source: string|null, is_active: bool}>  $rows
     */
    private function saveRows(array $rows): void
    {
        $this->info(sprintf('Saving %d appearances...', count($rows)));

        $count = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            WowAppearance::query()->upsert(
                $chunk,
                uniqueBy: ['id'],
                update: ['name_fr', 'slot', 'category', 'quality', 'item_id', 'icon_file_data_id', 'icon_url', 'expansion_id', 'source', 'is_active'],
            );
            $count += count($chunk);
            $this->info(sprintf('  Saved %d...', $count));
        }

        $this->info(sprintf('Appearance import complete: %d items.', $count));
    }

    /**
     * Détecte les items techniques/datamining non localisés (templates, placeholders, noms d'assets
     * internes) qui ne doivent pas peupler la garde-robe collectionnable.
     */
    private function isPlaceholderName(string $name): bool
    {
        // noms d'assets internes : "Cape_Cloth_Sindragosa_D_01"
        if (str_contains($name, '_')) {
            return true;
        }

        // templates versionnés : "10.0 Rare Reward TBD", "11.0 Raid Template"
        if (preg_match('/^\d+\.\d/', $name) === 1) {
            return true;
        }

        // mots-clés internes explicites
        return preg_match('/\b(Template|TBD|Placeholder|Do Not Use|Deprecated|UNUSED|Debug|Internal)\b/i', $name) === 1;
    }

    private function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }

        Log::info($message);
    }
}
