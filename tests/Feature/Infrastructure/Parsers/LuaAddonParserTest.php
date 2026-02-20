<?php

declare(strict_types=1);

use App\Infrastructure\Parsers\LuaAddonParser;

/**
 * Characterization tests for LuaAddonParser.
 *
 * These tests use minimal fixture files stored in storage during setup
 * to capture the current behavior before refactoring.
 */
beforeEach(function (): void {
    $this->parser = resolve(LuaAddonParser::class);

    // Ensure blizzard directory exists
    $dir = storage_path('app/blizzard');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
});

afterEach(function (): void {
    // Clean up test fixture files
    $files = [
        'area_table.csv', 'map.csv', 'content_tuning.csv',
        'quest_v2_cli_task.csv', 'skill_line_ability.csv', 'faction.csv',
    ];
    foreach ($files as $file) {
        $path = storage_path('app/blizzard/'.$file);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    $mappingsDir = storage_path('app/blizzard/mappings/processed');
    foreach (['achievements.json', 'quests.json'] as $file) {
        $path = $mappingsDir.'/'.$file;
        if (file_exists($path)) {
            unlink($path);
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
    // Area 100 on continent/map 530 (Outland, expansion 1)
    writeAreaTableCsv([
        ['100', '530', '0', '0'],
    ]);
    writeMapCsv([
        ['530', '1'], // Outland = TBC
    ]);
    writeContentTuningCsv([]);

    $map = $this->parser->buildAreaExpansionMap();

    expect($map[100])->toBe(1); // TBC
});

test('buildAreaExpansionMap uses ContentTuning for Classic continent zones', function (): void {
    // Area 200 on continent 0 (EK/Kalimdor, map expansion 0) with ContentTuning for Cata
    writeAreaTableCsv([
        ['200', '0', '0', '50'],
    ]);
    writeMapCsv([
        ['0', '0'], // Classic continent
    ]);
    writeContentTuningCsv([
        ['50', '3'], // ContentTuning 50 = Cataclysm
    ]);

    $map = $this->parser->buildAreaExpansionMap();

    expect($map[200])->toBe(3); // Cataclysm
});

test('buildAreaExpansionMap defaults Classic continent zone without ContentTuning to 0', function (): void {
    writeAreaTableCsv([
        ['300', '0', '0', '0'], // No ContentTuning
    ]);
    writeMapCsv([
        ['0', '0'],
    ]);
    writeContentTuningCsv([]);

    $map = $this->parser->buildAreaExpansionMap();

    expect($map[300])->toBe(0); // Classic default
});

test('buildAreaExpansionMap applies manual overrides', function (): void {
    // Area 2037 is Quel'thalas, overridden to TBC (1)
    writeAreaTableCsv([
        ['2037', '0', '0', '0'],
    ]);
    writeMapCsv([['0', '0']]);
    writeContentTuningCsv([]);

    $map = $this->parser->buildAreaExpansionMap();

    expect($map[2037])->toBe(1); // Override to TBC
});

test('buildAreaExpansionMap walks parent chain when map not found', function (): void {
    // Area 500 has parent 400, which is on Outland
    writeAreaTableCsv([
        ['400', '530', '0', '0'],
        ['500', '999', '400', '0'], // Map 999 not in map.csv
    ]);
    writeMapCsv([
        ['530', '1'], // Outland = TBC
    ]);
    writeContentTuningCsv([]);

    $map = $this->parser->buildAreaExpansionMap();

    expect($map[500])->toBe(1); // Resolved from parent → TBC
});

// ─── getAchievementExpansionMap ─────────────────────────────

test('getAchievementExpansionMap returns achievement to expansion mapping from JSON', function (): void {
    $dir = storage_path('app/blizzard/mappings/processed');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($dir.'/achievements.json', json_encode([
        '0' => ['total_ids' => [1, 2, 3], 'categories' => []],
        '7' => ['total_ids' => [100, 101], 'categories' => []],
    ]));

    $map = $this->parser->getAchievementExpansionMap();

    expect($map)->toHaveCount(5);
    expect($map[1])->toBe(0);
    expect($map[2])->toBe(0);
    expect($map[3])->toBe(0);
    expect($map[100])->toBe(7);
    expect($map[101])->toBe(7);
});

test('getAchievementExpansionMap returns empty array when file missing', function (): void {
    $map = $this->parser->getAchievementExpansionMap();

    expect($map)->toBe([]);
});

// ─── getQuestFactionMap ─────────────────────────────────────

test('getQuestFactionMap extracts Alliance and Horde quests from FiltRaces', function (): void {
    writeQuestV2CliTaskCsv([
        ['100', '0', '6130900294268439629'],   // Alliance
        ['101', '0', '-6184943489809468494'],  // Horde
        ['102', '0', '-1'],                     // Both (not in map)
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
    expect($map)->not->toHaveKey(3); // Neutral
});

// ─── getRecipeFactionMap ────────────────────────────────────

test('getRecipeFactionMap extracts faction from RaceMask', function (): void {
    writeSkillLineAbilityCsv([
        ['6130900294268439629', '', '', '5001', '171', '80001'],   // Alliance
        ['-6184943489809468494', '', '', '5002', '171', '80002'], // Horde
        ['0', '', '', '5003', '171', '80003'],                    // Both
    ]);

    $map = $this->parser->getRecipeFactionMap();

    expect($map)->toHaveCount(2);
    expect($map[5001])->toBe('Alliance');
    expect($map[5002])->toBe('Horde');
});

// ─── getReputationFactionMap ────────────────────────────────

test('getReputationFactionMap extracts faction from ReputationBase', function (): void {
    writeFactionCsv([
        ['1000', '100', '-1'],   // Alliance (alliance base >= 0, horde base < 0)
        ['1001', '-1', '100'],   // Horde (horde base >= 0, alliance base < 0)
        ['1002', '100', '100'],  // Neutral (both >= 0, not in map)
    ]);

    $map = $this->parser->getReputationFactionMap();

    expect($map)->toHaveCount(2);
    expect($map[1000])->toBe('Alliance');
    expect($map[1001])->toBe('Horde');
    expect($map)->not->toHaveKey(1002);
});

// ─── getQuestExpansionMap ───────────────────────────────────

test('getQuestExpansionMap only returns modern expansion quests (>= 10)', function (): void {
    writeContentTuningCsv([
        ['100', '10'],  // TWW
        ['101', '11'],  // Midnight
        ['102', '7'],   // BfA (not modern, excluded)
    ]);
    writeQuestV2CliTaskCsv([
        ['5000', '100', '-1'], // Quest with CT 100 → TWW
        ['5001', '101', '-1'], // Quest with CT 101 → Midnight
        ['5002', '102', '-1'], // Quest with CT 102 → BfA (excluded)
    ]);

    $map = $this->parser->getQuestExpansionMap();

    expect($map)->toHaveCount(2);
    expect($map[5000])->toBe(10);
    expect($map[5001])->toBe(11);
    expect($map)->not->toHaveKey(5002);
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
