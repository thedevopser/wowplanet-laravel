<?php

declare(strict_types=1);

namespace App\Infrastructure\Parsers;

use Illuminate\Support\Facades\Log;

final class SimpleArmoryParser
{
    private const ICON_BASE_URL = 'https://wow.zamimg.com/images/wow/icons/medium/';

    /**
     * @var array<string, int>
     */
    private const CATEGORY_EXPANSION_MAP = [
        'Classic' => 0,
        'The Burning Crusade' => 1,
        'Burning Crusade' => 1,
        'Wrath of the Lich King' => 2,
        'Cataclysm' => 3,
        'Mists of Pandaria' => 4,
        'Pandaria' => 4,
        'Warlords of Draenor' => 5,
        'Draenor' => 5,
        'Legion' => 6,
        'Battle for Azeroth' => 7,
        'Shadowlands' => 8,
        'Dragonflight' => 9,
        'The War Within' => 10,
        'War Within' => 10,
        'Midnight' => 11,
    ];

    /**
     * @var array<string, string>
     */
    private const FACTION_MAP = [
        'A' => 'Alliance',
        'H' => 'Horde',
    ];

    /**
     * Parse SimpleArmory achievements.json (4-level hierarchy: supercats > cats > subcats > items).
     *
     * @return array<int, array{category: string, subcategory: string, expansion_id: int|null, icon: string, points: int, faction: string|null}>
     */
    public static function parseAchievements(): array
    {
        $data = self::loadJsonFile('achievements.json');
        if ($data === null) {
            return [];
        }

        if (! isset($data['supercats']) || ! is_array($data['supercats'])) {
            Log::warning('SimpleArmory achievements.json: missing or invalid "supercats" key.');

            return [];
        }

        /** @var list<array<string, mixed>> $supercats */
        $supercats = $data['supercats'];

        $items = [];
        $skipped = 0;

        foreach ($supercats as $supercat) {
            $parsed = self::parseAchievementSupercat($supercat, $skipped);
            foreach ($parsed as $id => $item) {
                $items[$id] = $item;
            }
        }

        if ($skipped > 0) {
            Log::info(sprintf('SimpleArmory achievements: skipped %d not-yet-released items.', $skipped));
        }

        return $items;
    }

    /**
     * Parse a SimpleArmory collection file (mounts.json, pets.json, decors.json).
     *
     * @return array<int, array{category: string, source: string, icon: string|null, faction: string|null, spellid: int, creatureId: int, itemId: int|null, notObtainable: bool}>
     */
    public static function parseCollection(string $filename): array
    {
        $data = self::loadJsonFile($filename);
        if ($data === null) {
            return [];
        }

        if (isset($data['supercats'])) {
            Log::warning(sprintf('SimpleArmory %s: invalid structure (expected array of categories).', $filename));

            return [];
        }

        $items = [];
        $skipped = 0;

        foreach ($data as $category) {
            if (! is_array($category)) {
                continue;
            }

            /** @var array<string, mixed> $category */
            $parsed = self::parseCollectionCategory($category, $skipped);
            foreach ($parsed as $id => $item) {
                $items[$id] = $item;
            }
        }

        if ($skipped > 0) {
            Log::info(sprintf('SimpleArmory %s: skipped %d not-yet-released items.', $filename, $skipped));
        }

        return $items;
    }

    /**
     * Build a Wowhead CDN URL from a SimpleArmory icon asset name.
     * Accepts text names (e.g. "ability_mount_drake_blue") and numeric FileDataIDs.
     */
    public static function buildIconUrl(string $iconName): ?string
    {
        $trimmed = trim($iconName);
        if ($trimmed === '') {
            return null;
        }

        return self::ICON_BASE_URL.strtolower($trimmed).'.jpg';
    }

    /**
     * Map a SimpleArmory category name to an expansion ID.
     */
    public static function resolveExpansionId(string $categoryName): ?int
    {
        return self::CATEGORY_EXPANSION_MAP[$categoryName] ?? null;
    }

    /**
     * @param  array<string, mixed>  $supercat
     * @return array<int, array{category: string, subcategory: string, expansion_id: int|null, icon: string, points: int, faction: string|null}>
     */
    private static function parseAchievementSupercat(array $supercat, int &$skipped): array
    {
        $supercatName = self::extractString($supercat, 'name');
        if ($supercatName === '') {
            return [];
        }

        $items = [];

        /** @var list<array<string, mixed>> $cats */
        $cats = $supercat['cats'] ?? [];

        foreach ($cats as $cat) {
            $catName = self::extractString($cat, 'name');
            $expansionId = self::CATEGORY_EXPANSION_MAP[$catName] ?? null;

            /** @var list<array<string, mixed>> $subcats */
            $subcats = $cat['subcats'] ?? [];

            foreach ($subcats as $subcat) {
                $parsed = self::parseAchievementSubcat($subcat, $supercatName, $expansionId, $skipped);
                foreach ($parsed as $id => $item) {
                    $items[$id] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $subcat
     * @return array<int, array{category: string, subcategory: string, expansion_id: int|null, icon: string, points: int, faction: string|null}>
     */
    private static function parseAchievementSubcat(
        array $subcat,
        string $supercatName,
        ?int $expansionId,
        int &$skipped,
    ): array {
        $subcatName = self::extractString($subcat, 'name');
        $items = [];

        /** @var list<array<string, mixed>> $rawItems */
        $rawItems = $subcat['items'] ?? [];

        foreach ($rawItems as $rawItem) {

            if (! empty($rawItem['notReleased'])) {
                $skipped++;

                continue;
            }

            $id = self::extractInt($rawItem, 'id');
            if ($id <= 0) {
                continue;
            }

            $items[$id] = [
                'category' => $supercatName,
                'subcategory' => $subcatName,
                'expansion_id' => $expansionId,
                'icon' => self::extractString($rawItem, 'icon'),
                'points' => max(0, self::extractInt($rawItem, 'points')),
                'faction' => self::mapFaction($rawItem),
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $category
     * @return array<int, array{category: string, source: string, icon: string|null, faction: string|null, spellid: int, creatureId: int, itemId: int|null, notObtainable: bool}>
     */
    private static function parseCollectionCategory(array $category, int &$skipped): array
    {
        $categoryName = self::extractString($category, 'name');
        $items = [];

        /** @var list<array<string, mixed>> $subcats */
        $subcats = $category['subcats'] ?? [];

        foreach ($subcats as $subcat) {
            $sourceName = self::extractString($subcat, 'name');

            /** @var list<array<string, mixed>> $rawItems */
            $rawItems = $subcat['items'] ?? [];

            foreach ($rawItems as $rawItem) {

                if (! empty($rawItem['notReleased'])) {
                    $skipped++;

                    continue;
                }

                $id = self::extractInt($rawItem, 'ID');
                if ($id <= 0) {
                    continue;
                }

                $items[$id] = [
                    'category' => $categoryName,
                    'source' => $sourceName,
                    'icon' => self::extractNullableString($rawItem, 'icon'),
                    'faction' => self::mapFaction($rawItem),
                    'spellid' => self::extractInt($rawItem, 'spellid'),
                    'creatureId' => self::extractInt($rawItem, 'creatureId'),
                    'itemId' => self::extractNullableInt($rawItem, 'itemId'),
                    'notObtainable' => ! empty($rawItem['notObtainable']),
                ];
            }
        }

        return $items;
    }

    /**
     * @return array<string|int, mixed>|null
     */
    private static function loadJsonFile(string $filename): ?array
    {
        $path = storage_path('app/blizzard/'.$filename);
        if (! file_exists($path)) {
            Log::warning(sprintf('SimpleArmory file not found: %s', $filename));

            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            Log::warning(sprintf('SimpleArmory file unreadable: %s', $filename));

            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            Log::warning(sprintf('SimpleArmory invalid JSON in %s: %s', $filename, $jsonException->getMessage()));

            return null;
        }

        if (! is_array($decoded)) {
            Log::warning(sprintf('SimpleArmory %s: expected array, got %s.', $filename, gettype($decoded)));

            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function extractString(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function extractNullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function extractInt(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;

        if (is_int($value)) {
            return $value;
        }

        return (int) (is_string($value) || is_float($value) ? $value : 0);
    }

    /**
     * Extract an optional integer, handling SimpleArmory's inconsistent types (string for decors, int for others).
     *
     * @param  array<string, mixed>  $data
     */
    private static function extractNullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        $intValue = (int) (is_string($value) || is_float($value) ? $value : 0);

        return $intValue > 0 ? $intValue : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function mapFaction(array $data): ?string
    {
        $side = $data['side'] ?? null;
        if (! is_string($side)) {
            return null;
        }

        return self::FACTION_MAP[$side] ?? null;
    }
}
