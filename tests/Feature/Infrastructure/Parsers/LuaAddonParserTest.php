<?php

declare(strict_types=1);

use App\Infrastructure\Parsers\LuaAddonParser;

beforeEach(function (): void {
    $this->parser = resolve(LuaAddonParser::class);

    $dir = storage_path('app/blizzard');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Back up real files to avoid interference with test data
    $this->testFiles = [
        'area_table.csv', 'map.csv', 'content_tuning.csv',
        'quest_v2_cli_task.csv', 'skill_line_ability.csv', 'faction.csv',
        'achievement.csv', 'achievement_category.csv', 'criteria_tree.csv',
        'spell_name.csv', 'ui_map.csv', 'quest_poi_blob.csv',
    ];
    $this->backups = [];
    foreach ($this->testFiles as $file) {
        $path = $dir.'/'.$file;
        if (file_exists($path)) {
            $backup = $path.'.testbak';
            rename($path, $backup);
            $this->backups[$path] = $backup;
        }
    }
});

afterEach(function (): void {
    foreach ($this->testFiles as $file) {
        $path = storage_path('app/blizzard/'.$file);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    // Restore real files
    foreach ($this->backups as $path => $backup) {
        if (file_exists($backup)) {
            rename($backup, $path);
        }
    }
});

// ─── normalizeApostrophes ───────────────────────────────────

test('normalizeApostrophes replaces curly apostrophes with straight ones', function (): void {
    expect(LuaAddonParser::normalizeApostrophes("Forêt d\u{2019}Elwynn"))->toBe("Forêt d'Elwynn");
    expect(LuaAddonParser::normalizeApostrophes("Ahn\u{2019}Qiraj"))->toBe("Ahn'Qiraj");
});

test('normalizeApostrophes replaces non-breaking spaces with regular spaces', function (): void {
    expect(LuaAddonParser::normalizeApostrophes("Silithus\u{00A0}: la Plaie"))->toBe('Silithus : la Plaie');
});

test('normalizeApostrophes replaces smart quotes with regular quotes', function (): void {
    expect(LuaAddonParser::normalizeApostrophes("\u{201C}test\u{201D}"))->toBe('"test"');
    expect(LuaAddonParser::normalizeApostrophes("\u{2018}test\u{2019}"))->toBe("'test'");
});

// ─── buildAreaExpansionMap ───────────────────────────────────

test('buildAreaExpansionMap resolves expansion from Map.ExpansionID for expansion continents', function (): void {
    writeAreaTableCsv([
        ['100', '530', '0', '0'],
    ]);
    writeMapCsv([
        ['530', '1'],
    ]);
    writeContentTuningCsv([]);

    $map = $this->parser->buildAreaExpansionMap();

    expect($map[100])->toBe(1);
});

test('buildAreaExpansionMap uses ContentTuning for Classic continent zones', function (): void {
    writeAreaTableCsv([
        ['200', '0', '0', '50'],
    ]);
    writeMapCsv([
        ['0', '0'],
    ]);
    writeContentTuningCsv([
        ['50', '3'],
    ]);

    $map = $this->parser->buildAreaExpansionMap();

    expect($map[200])->toBe(3);
});

test('buildAreaExpansionMap defaults Classic continent zone without ContentTuning to 0', function (): void {
    writeAreaTableCsv([
        ['300', '0', '0', '0'],
    ]);
    writeMapCsv([
        ['0', '0'],
    ]);
    writeContentTuningCsv([]);

    $map = $this->parser->buildAreaExpansionMap();

    expect($map[300])->toBe(0);
});

test('buildAreaExpansionMap applies manual overrides', function (): void {
    writeAreaTableCsv([
        ['2037', '0', '0', '0'],
    ]);
    writeMapCsv([['0', '0']]);
    writeContentTuningCsv([]);

    $map = $this->parser->buildAreaExpansionMap();

    expect($map[2037])->toBe(1);
});

test('buildAreaExpansionMap walks parent chain when map not found', function (): void {
    writeAreaTableCsv([
        ['400', '530', '0', '0'],
        ['500', '999', '400', '0'],
    ]);
    writeMapCsv([
        ['530', '1'],
    ]);
    writeContentTuningCsv([]);

    $map = $this->parser->buildAreaExpansionMap();

    expect($map[500])->toBe(1);
});

// ─── getAchievementExpansionMap (DB2-based) ─────────────────

test('getAchievementExpansionMap returns mapping from DB2 category hierarchy', function (): void {
    lapWriteCategoryCsv([
        ['92', 'Quêtes', '-1'],
        ['14861', 'Norfendre', '92'],
    ]);
    lapWriteAchievementCsv([
        ['100', '14861', '0'],
    ]);

    $map = $this->parser->getAchievementExpansionMap();

    expect($map[100])->toBe(2);
});

test('getAchievementExpansionMap returns empty array when files missing', function (): void {
    $map = $this->parser->getAchievementExpansionMap();

    expect($map)->toBe([]);
});

// ─── getQuestFactionMap ─────────────────────────────────────

test('getQuestFactionMap extracts Alliance and Horde quests from FiltRaces', function (): void {
    writeQuestV2CliTaskCsv([
        ['100', '0', '6130900294268439629'],
        ['101', '0', '-6184943489809468494'],
        ['102', '0', '-1'],
    ]);

    $map = $this->parser->getQuestFactionMap();

    expect($map)->toHaveCount(2);
    expect($map[100])->toBe('Alliance');
    expect($map[101])->toBe('Horde');
    expect($map)->not->toHaveKey(102);
});

test('getQuestFactionMap returns empty array when file missing', function (): void {
    expect($this->parser->getQuestFactionMap())->toBe([]);
});

// ─── getZoneFactionMap ──────────────────────────────────────

test('getZoneFactionMap extracts Alliance and Horde zones from FactionGroupMask', function (): void {
    writeAreaTableCsv([
        ['1', '0', '0', '0'],
        ['2', '0', '0', '0'],
        ['3', '0', '0', '0'],
    ], includeFactionGroupMask: true, factionGroupMasks: [2, 4, 0]);

    $map = $this->parser->getZoneFactionMap();

    expect($map[1])->toBe('Alliance');
    expect($map[2])->toBe('Horde');
    expect($map)->not->toHaveKey(3);
});

// ─── getRecipeFactionMap ────────────────────────────────────

test('getRecipeFactionMap extracts faction from RaceMask', function (): void {
    writeSkillLineAbilityCsv([
        ['6130900294268439629', '', '', '5001', '171', '80001'],
        ['-6184943489809468494', '', '', '5002', '171', '80002'],
        ['0', '', '', '5003', '171', '80003'],
    ]);

    $map = $this->parser->getRecipeFactionMap();

    expect($map)->toHaveCount(2);
    expect($map[5001])->toBe('Alliance');
    expect($map[5002])->toBe('Horde');
});

// ─── getReputationFactionMap ────────────────────────────────

test('getReputationFactionMap extracts faction from ReputationBase', function (): void {
    writeFactionCsv([
        ['1000', '100', '-1'],
        ['1001', '-1', '100'],
        ['1002', '100', '100'],
    ]);

    $map = $this->parser->getReputationFactionMap();

    expect($map)->toHaveCount(2);
    expect($map[1000])->toBe('Alliance');
    expect($map[1001])->toBe('Horde');
    expect($map)->not->toHaveKey(1002);
});

// ─── getQuestExpansionMap ───────────────────────────────────

test('getQuestExpansionMap returns all expansion quests from ContentTuning', function (): void {
    writeContentTuningCsv([
        ['100', '10'],
        ['101', '11'],
        ['102', '7'],
    ]);
    writeQuestV2CliTaskCsv([
        ['5000', '100', '-1'],
        ['5001', '101', '-1'],
        ['5002', '102', '-1'],
    ]);

    $map = $this->parser->getQuestExpansionMap();

    expect($map)->toHaveCount(3);
    expect($map[5000])->toBe(10);
    expect($map[5001])->toBe(11);
    expect($map[5002])->toBe(7);
});

// ─── getSpellNameMap ────────────────────────────────────────

test('getSpellNameMap loads spell names from CSV', function (): void {
    file_put_contents(storage_path('app/blizzard/spell_name.csv'), implode("\n", [
        'ID,Name_lang',
        '50001,Invocation : Dragonnet',
        '50002,Potion de vie',
    ]));

    $map = $this->parser->getSpellNameMap();

    expect($map)->toHaveCount(2);
    expect($map[50001])->toBe('Invocation : Dragonnet');
    expect($map[50002])->toBe('Potion de vie');
});

test('getSpellNameMap returns empty when file missing', function (): void {
    expect($this->parser->getSpellNameMap())->toBe([]);
});

// ─── getQuestZoneMap ────────────────────────────────────────

test('getQuestZoneMap returns empty when files missing', function (): void {
    expect($this->parser->getQuestZoneMap())->toBe([]);
});

// ─── Helpers ────────────────────────────────────────────────

function writeAreaTableCsv(array $rows, bool $includeFactionGroupMask = false, array $factionGroupMasks = []): void
{
    $headers = $includeFactionGroupMask
        ? 'ID,ContinentID,ParentAreaID,ContentTuningID,FactionGroupMask'
        : 'ID,ContinentID,ParentAreaID,ContentTuningID';

    $lines = [$headers];
    foreach ($rows as $i => $row) {
        $line = implode(',', $row);
        if ($includeFactionGroupMask) {
            $line .= ','.($factionGroupMasks[$i] ?? '0');
        }

        $lines[] = $line;
    }

    file_put_contents(storage_path('app/blizzard/area_table.csv'), implode("\n", $lines));
}

function writeMapCsv(array $rows): void
{
    $lines = ['ID,ExpansionID'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    file_put_contents(storage_path('app/blizzard/map.csv'), implode("\n", $lines));
}

function writeContentTuningCsv(array $rows): void
{
    $lines = ['ID,ExpansionID'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    file_put_contents(storage_path('app/blizzard/content_tuning.csv'), implode("\n", $lines));
}

function writeQuestV2CliTaskCsv(array $rows): void
{
    $lines = ['ID,ContentTuningID,FiltRaces'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    file_put_contents(storage_path('app/blizzard/quest_v2_cli_task.csv'), implode("\n", $lines));
}

function writeSkillLineAbilityCsv(array $rows): void
{
    $lines = ['RaceMask,AbilityVerb_lang,AbilityAllVerb_lang,ID,SkillLine,Spell'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    file_put_contents(storage_path('app/blizzard/skill_line_ability.csv'), implode("\n", $lines));
}

function writeFactionCsv(array $rows): void
{
    $lines = ['ID,ReputationBase_0,ReputationBase_1'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    file_put_contents(storage_path('app/blizzard/faction.csv'), implode("\n", $lines));
}

function lapWriteAchievementCsv(array $rows): void
{
    $lines = ['Description_lang,Title_lang,Reward_lang,ID,Instance_ID,Faction,Supercedes,Category,Minimum_criteria,Points,Flags,Ui_order,IconFileID,RewardItemID,Criteria_tree,Shares_criteria,CovenantID,HiddenBeforeDisplaySeason,LegacyAfterTimeEvent'];
    foreach ($rows as $row) {
        $lines[] = sprintf(',,,"%s","%s","-1","0","%s","0","0","0","0","0","0","0","0","0","0","0"', $row[0], $row[2], $row[1]);
    }

    file_put_contents(storage_path('app/blizzard/achievement.csv'), implode("\n", $lines));
}

function lapWriteCategoryCsv(array $rows): void
{
    $lines = ['Name_lang,ID,Parent,Ui_order'];
    foreach ($rows as $row) {
        $lines[] = sprintf('"%s","%s","%s","0"', $row[1], $row[0], $row[2]);
    }

    file_put_contents(storage_path('app/blizzard/achievement_category.csv'), implode("\n", $lines));
}
