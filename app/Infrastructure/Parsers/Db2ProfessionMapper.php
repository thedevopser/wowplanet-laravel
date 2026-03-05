<?php

declare(strict_types=1);

namespace App\Infrastructure\Parsers;

use App\Infrastructure\Blizzard\ExpansionTierMatcher;
use Illuminate\Support\Facades\File;

class Db2ProfessionMapper
{
    private const SECONDARY_PROFESSION_IDS = [185, 356, 794];

    /**
     * Build profession and recipe data from DB2 CSVs.
     *
     * @param  array<int, string>  $spellNameMap  [spellId => spellName]
     * @return array{
     *     professions: list<array{id: int, name_fr: string, type: string}>,
     *     recipes: list<array{id: int, name_fr: string, profession_id: int, expansion_id: int, category_name: string, wowhead_spell_id: int}>,
     * }
     */
    public static function build(array $spellNameMap): array
    {
        $skillLines = self::parseSkillLineCsv();
        if ($skillLines === []) {
            return ['professions' => [], 'recipes' => []];
        }

        // Identify root professions (CategoryID=11, ParentSkillLineID=0)
        /** @var array<int, array{id: int, name_fr: string, type: string}> $professions */
        $professions = [];
        /** @var array<int, true> $professionIds */
        $professionIds = [];
        foreach ($skillLines as $id => $line) {
            if ($line['parent'] === 0 && ($line['category'] === 11 || in_array($id, self::SECONDARY_PROFESSION_IDS, true))) {
                $professions[$id] = [
                    'id' => $id,
                    'name_fr' => $line['name'],
                    'type' => in_array($id, self::SECONDARY_PROFESSION_IDS, true) ? 'secondary' : 'primary',
                ];
                $professionIds[$id] = true;
            }
        }

        // Parse recipe-related CSVs
        $abilities = self::parseSkillLineAbilityCsv();
        $categories = self::parseTradeSkillCategoryTree();

        // Build recipes from SkillLineAbility entries that belong to known professions
        $recipes = [];
        foreach ($abilities as $ability) {
            $professionId = $ability['skill_line'];
            if (! isset($professionIds[$professionId])) {
                continue;
            }

            $recipeName = $spellNameMap[$ability['spell']] ?? '';
            if ($recipeName === '') {
                continue;
            }

            $catId = $ability['trade_skill_category_id'];
            $catInfo = self::resolveCategoryExpansion($catId, $categories);

            $recipes[] = [
                'id' => $ability['id'],
                'name_fr' => $recipeName,
                'profession_id' => $professionId,
                'expansion_id' => $catInfo['expansion_id'],
                'category_name' => $catInfo['category_name'],
                'wowhead_spell_id' => $ability['spell'],
            ];
        }

        return [
            'professions' => array_values($professions),
            'recipes' => $recipes,
        ];
    }

    /**
     * Walk up the TradeSkillCategory hierarchy to find the expansion and category name.
     *
     * Hierarchy is typically: Root Title (parent=0) → Collection → Specific Category
     * The expansion is deduced from the root title's name.
     * The category name is the recipe's direct category.
     *
     * @param  array<int, array{name: string, parent: int}>  $categories
     * @return array{expansion_id: int, category_name: string}
     */
    private static function resolveCategoryExpansion(int $catId, array $categories): array
    {
        if ($catId === 0 || ! isset($categories[$catId])) {
            return ['expansion_id' => 0, 'category_name' => ''];
        }

        $categoryName = $categories[$catId]['name'];

        // Walk up to root to find expansion
        $current = $catId;
        $maxDepth = 10;
        while ($maxDepth-- > 0) {
            $cat = $categories[$current] ?? null;
            if ($cat === null) {
                break;
            }

            $expansion = ExpansionTierMatcher::match($cat['name']);
            if ($expansion !== null) {
                return ['expansion_id' => $expansion, 'category_name' => $categoryName];
            }

            if ($cat['parent'] === 0) {
                break;
            }

            $current = $cat['parent'];
        }

        return ['expansion_id' => 0, 'category_name' => $categoryName];
    }

    /**
     * @return array<int, array{name: string, category: int, parent: int}>
     */
    private static function parseSkillLineCsv(): array
    {
        $csvPath = storage_path('app/blizzard/skill_line.csv');
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
        $nameIdx = (int) array_search('DisplayName_lang', $headers, true);
        $categoryIdx = (int) array_search('CategoryID', $headers, true);
        $parentIdx = (int) array_search('ParentSkillLineID', $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $map[(int) $row[$idIdx]] = [
                'name' => trim($row[$nameIdx] ?? ''),
                'category' => (int) ($row[$categoryIdx] ?? 0),
                'parent' => (int) ($row[$parentIdx] ?? 0),
            ];
        }

        fclose($handle);

        return $map;
    }

    /**
     * @return list<array{id: int, skill_line: int, spell: int, trade_skill_category_id: int}>
     */
    private static function parseSkillLineAbilityCsv(): array
    {
        $csvPath = storage_path('app/blizzard/skill_line_ability.csv');
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
        $skillLineIdx = (int) array_search('SkillLine', $headers, true);
        $spellIdx = (int) array_search('Spell', $headers, true);
        $categoryIdx = (int) array_search('TradeSkillCategoryID', $headers, true);

        $abilities = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $spell = (int) ($row[$spellIdx] ?? 0);
            if ($spell <= 0) {
                continue;
            }

            $abilities[] = [
                'id' => (int) $row[$idIdx],
                'skill_line' => (int) $row[$skillLineIdx],
                'spell' => $spell,
                'trade_skill_category_id' => (int) ($row[$categoryIdx] ?? 0),
            ];
        }

        fclose($handle);

        return $abilities;
    }

    /**
     * @return array<int, array{name: string, parent: int}>
     */
    private static function parseTradeSkillCategoryTree(): array
    {
        $csvPath = storage_path('app/blizzard/trade_skill_category.csv');
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
        $parentIdx = (int) array_search('ParentTradeSkillCategoryID', $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $map[(int) $row[$idIdx]] = [
                'name' => trim($row[$nameIdx] ?? ''),
                'parent' => (int) ($row[$parentIdx] ?? 0),
            ];
        }

        fclose($handle);

        return $map;
    }
}
