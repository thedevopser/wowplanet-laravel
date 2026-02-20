<?php

declare(strict_types=1);

use App\Infrastructure\Parsers\AddonDataParser;

beforeEach(function (): void {
    $this->parser = new AddonDataParser;

    $dir = storage_path('app/blizzard');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Back up real files to avoid interference with test data
    $this->testFiles = [
        'content_tuning.csv', 'quest_v2_cli_task.csv', 'faction.csv', 'area_table.csv',
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

// ─── getZoneExpansionMap ────────────────────────────────────

test('getZoneExpansionMap returns supplementary zones from config', function (): void {
    config(['wow_zones' => ['Vallée de Strangleronce' => 0, 'Nagrand' => 5]]);

    $map = $this->parser->getZoneExpansionMap();

    expect($map)->toHaveKey('vallée de strangleronce');
    expect($map['vallée de strangleronce'])->toBe(0);
    expect($map['nagrand'])->toBe(5);
});

test('getZoneExpansionMap returns empty when no config data', function (): void {
    config(['wow_zones' => []]);

    $map = $this->parser->getZoneExpansionMap();

    expect($map)->toBe([]);
});

test('getZoneExpansionMap normalizes apostrophes', function (): void {
    config(['wow_zones' => ["Vallée d\u{2019}Alterac" => 0]]);

    $map = $this->parser->getZoneExpansionMap();

    expect($map)->toHaveKey("vallée d'alterac");
});

// ─── getQuestExpansionMap (DB2-based) ───────────────────────

test('getQuestExpansionMap returns all expansion overrides from DB2', function (): void {
    adpWriteContentTuningCsv([
        ['2797', '10'],
        ['2798', '11'],
        ['100', '3'],
    ]);

    adpWriteQuestCliTaskCsv([
        ['80000', '2797'],
        ['80001', '2798'],
        ['80002', '100'],
    ]);

    $map = $this->parser->getQuestExpansionMap();

    expect($map)->toHaveCount(3);
    expect($map[80000])->toBe(10);
    expect($map[80001])->toBe(11);
    expect($map[80002])->toBe(3);
});

test('getQuestExpansionMap returns empty when no CSV data', function (): void {
    adpWriteContentTuningCsv([['100', '10']]);

    $map = $this->parser->getQuestExpansionMap();

    expect($map)->toBe([]);
});

test('getQuestExpansionMap skips quests with zero ContentTuningID', function (): void {
    adpWriteContentTuningCsv([['2797', '10']]);
    adpWriteQuestCliTaskCsv([
        ['80000', '0'],
        ['80001', '2797'],
    ]);

    $map = $this->parser->getQuestExpansionMap();

    expect($map)->toHaveCount(1);
    expect($map[80001])->toBe(10);
});

// ─── getReputationFactionMap ────────────────────────────────

test('getReputationFactionMap returns empty when file missing', function (): void {
    expect($this->parser->getReputationFactionMap())->toBe([]);
});

test('getReputationFactionMap handles both negative bases as neutral', function (): void {
    adpWriteFactionCsv([['500', '-1', '-1']]);

    expect($this->parser->getReputationFactionMap())->toBe([]);
});

test('getReputationFactionMap detects alliance and horde', function (): void {
    adpWriteFactionCsv([
        ['1', '100', '-1'],
        ['2', '-1', '100'],
        ['3', '100', '100'],
    ]);

    $map = $this->parser->getReputationFactionMap();

    expect($map)->toHaveCount(2);
    expect($map[1])->toBe('Alliance');
    expect($map[2])->toBe('Horde');
});

// ─── getZoneFactionMap ──────────────────────────────────────

test('getZoneFactionMap returns empty when file missing', function (): void {
    expect($this->parser->getZoneFactionMap())->toBe([]);
});

test('getZoneFactionMap parses FactionGroupMask', function (): void {
    adpWriteAreaTableCsv([
        ['1', '0', '0', '0', '2'],
        ['2', '0', '0', '0', '4'],
        ['3', '0', '0', '0', '0'],
    ]);

    $map = $this->parser->getZoneFactionMap();

    expect($map)->toHaveCount(2);
    expect($map[1])->toBe('Alliance');
    expect($map[2])->toBe('Horde');
});

// ─── Helpers (prefixed to avoid Pest global conflicts) ──────

function adpWriteContentTuningCsv(array $rows): void
{
    $lines = ['ID,ExpansionID'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    file_put_contents(storage_path('app/blizzard/content_tuning.csv'), implode("\n", $lines));
}

function adpWriteQuestCliTaskCsv(array $rows): void
{
    $lines = ['ID,ContentTuningID'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    file_put_contents(storage_path('app/blizzard/quest_v2_cli_task.csv'), implode("\n", $lines));
}

function adpWriteFactionCsv(array $rows): void
{
    $lines = ['ID,ReputationBase_0,ReputationBase_1'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    file_put_contents(storage_path('app/blizzard/faction.csv'), implode("\n", $lines));
}

function adpWriteAreaTableCsv(array $rows): void
{
    $lines = ['ID,ContinentID,ParentAreaID,ContentTuningID,FactionGroupMask'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    file_put_contents(storage_path('app/blizzard/area_table.csv'), implode("\n", $lines));
}
