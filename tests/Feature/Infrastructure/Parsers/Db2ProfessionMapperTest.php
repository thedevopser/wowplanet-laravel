<?php

declare(strict_types=1);

use App\Infrastructure\Parsers\Db2ProfessionMapper;

beforeEach(function (): void {
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

test('it builds professions and recipes from CSV data', function (): void {
    // SkillLine: ID=164, Name="Forge", CategoryID=11, ParentSkillLineID=0 → primary profession
    profWriteSkillLineCsv([
        ['164', 'Forge', '11', '0'],
    ]);

    // SkillLineAbility: ability ID=1, SkillLine=164, Spell=2018, TradeSkillCategoryID=100
    profWriteSkillLineAbilityCsv([
        ['1', '164', '2018', '100'],
    ]);

    // TradeSkillCategory: ID=100, Name="Armes", ParentTradeSkillCategoryID=50
    // Parent ID=50, Name="Classique Forge", ParentTradeSkillCategoryID=0
    profWriteTradeSkillCategoryCsv([
        ['50', 'Classique Forge', '0'],
        ['100', 'Armes', '50'],
    ]);

    $spellNameMap = [2018 => 'Barres de cuivre'];

    $result = Db2ProfessionMapper::build($spellNameMap);

    expect($result['professions'])->toHaveCount(1)
        ->and($result['professions'][0]['id'])->toBe(164)
        ->and($result['professions'][0]['name_fr'])->toBe('Forge')
        ->and($result['professions'][0]['type'])->toBe('primary')
        ->and($result['recipes'])->toHaveCount(1)
        ->and($result['recipes'][0]['name_fr'])->toBe('Barres de cuivre')
        ->and($result['recipes'][0]['profession_id'])->toBe(164)
        ->and($result['recipes'][0]['expansion_id'])->toBe(0)
        ->and($result['recipes'][0]['category_name'])->toBe('Armes');
});

test('it returns empty when no skill lines found', function (): void {
    $result = Db2ProfessionMapper::build([]);

    expect($result)->toBe(['professions' => [], 'recipes' => []]);
});

test('it identifies secondary professions', function (): void {
    // Secondary profession IDs: 185 (Cuisine), 356 (Pêche), 794 (Archéologie)
    profWriteSkillLineCsv([
        ['185', 'Cuisine', '9', '0'],
        ['356', 'Pêche', '9', '0'],
        ['794', 'Archéologie', '9', '0'],
    ]);

    profWriteSkillLineAbilityCsv([]);
    profWriteTradeSkillCategoryCsv([]);

    $result = Db2ProfessionMapper::build([]);

    expect($result['professions'])->toHaveCount(3);

    $types = array_column($result['professions'], 'type');
    expect($types)->each->toBe('secondary');

    $ids = array_column($result['professions'], 'id');
    expect($ids)->toContain(185)
        ->and($ids)->toContain(356)
        ->and($ids)->toContain(794);
});

// ─── Helpers ─────────────────────────────────────────────────

/**
 * Write skill_line.csv fixture.
 * $row = [ID, DisplayName_lang, CategoryID, ParentSkillLineID]
 *
 * @param  list<array{0: string, 1: string, 2: string, 3: string}>  $rows
 */
function profWriteSkillLineCsv(array $rows): void
{
    $lines = ['ID,DisplayName_lang,AlternateVerb_lang,HordeDisplayName_lang,OverrideDescription_lang,CategoryID,CanLink,ParentSkillLineID,Flags'];
    foreach ($rows as $row) {
        $lines[] = sprintf(
            '%s,"%s","","","" ,%s,0,%s,0',
            $row[0],
            $row[1],
            $row[2],
            $row[3],
        );
    }

    file_put_contents(storage_path('app/blizzard/skill_line.csv'), implode("\n", $lines));
}

/**
 * Write skill_line_ability.csv fixture.
 * $row = [ID, SkillLine, Spell, TradeSkillCategoryID]
 *
 * @param  list<array{0: string, 1: string, 2: string, 3: string}>  $rows
 */
function profWriteSkillLineAbilityCsv(array $rows): void
{
    $lines = ['ID,Spell,SupercedesSpell,SkillLine,TrivialSkillLineRankHigh,TrivialSkillLineRankLow,UniqueBit,TradeSkillCategoryID,NumSkillUps,ClassMask,MinSkillLineRank,AcquireMethod,Flags,SkillupSkillLineID'];
    foreach ($rows as $row) {
        $lines[] = sprintf(
            '%s,%s,0,%s,0,0,0,%s,0,0,0,0,0,0',
            $row[0],
            $row[2],
            $row[1],
            $row[3],
        );
    }

    file_put_contents(storage_path('app/blizzard/skill_line_ability.csv'), implode("\n", $lines));
}

/**
 * Write trade_skill_category.csv fixture.
 * $row = [ID, Name_lang, ParentTradeSkillCategoryID]
 *
 * @param  list<array{0: string, 1: string, 2: string}>  $rows
 */
function profWriteTradeSkillCategoryCsv(array $rows): void
{
    $lines = ['ID,Name_lang,OrderIndex,Flags,HordeName_lang,ParentTradeSkillCategoryID,SkillLineID'];
    foreach ($rows as $row) {
        $lines[] = sprintf(
            '%s,"%s",0,0,"",%s,0',
            $row[0],
            $row[1],
            $row[2],
        );
    }

    file_put_contents(storage_path('app/blizzard/trade_skill_category.csv'), implode("\n", $lines));
}
