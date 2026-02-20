<?php

declare(strict_types=1);

use App\Infrastructure\Parsers\AddonDataParser;

beforeEach(function (): void {
    $this->parser = new AddonDataParser;

    $dirs = [
        storage_path('app/blizzard'),
        storage_path('app/blizzard/mappings/processed'),
    ];
    foreach ($dirs as $dir) {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
});

afterEach(function (): void {
    $files = [
        'content_tuning.csv', 'quest_v2_cli_task.csv', 'faction.csv',
    ];
    foreach ($files as $file) {
        $path = storage_path('app/blizzard/'.$file);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    foreach (['achievements.json', 'quests.json'] as $file) {
        $path = storage_path('app/blizzard/mappings/processed/'.$file);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    // Cleanup BTW directories
    $btwDir = storage_path('app/blizzard/mappings/BTW');
    if (is_dir($btwDir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($btwDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($btwDir);
    }
});

// ─── getZoneExpansionMap ────────────────────────────────────

test('getZoneExpansionMap returns zones from quests.json', function (): void {
    writeQuestsJson([
        '0' => ['zones' => ['Durotar' => ['ids' => [1, 2]], 'Elwynn Forest' => ['ids' => [3]]]],
        '10' => ['zones' => ['Azj-Kahet' => ['ids' => [100]]]],
    ]);

    $map = $this->parser->getZoneExpansionMap();

    expect($map['durotar'])->toBe(0);
    expect($map['elwynn forest'])->toBe(0);
    expect($map['azj-kahet'])->toBe(10);
});

test('getZoneExpansionMap includes BTW index zones', function (): void {
    $btwDir = storage_path('app/blizzard/mappings/BTW/BtWQuestsTheWarWithin/Database');
    mkdir($btwDir, 0755, true);

    file_put_contents($btwDir.'/Index.frFR.lua', <<<'LUA'
    {
        name = "Dornogal",
        type = "category",
    },
    {
        name = "Isle of Dorn",
        type = "category",
    },
    LUA);

    $map = $this->parser->getZoneExpansionMap();

    expect($map['dornogal'])->toBe(10);
    expect($map['isle of dorn'])->toBe(10);
});

test('getZoneExpansionMap adds supplementary zones from config', function (): void {
    config(['wow_zones' => ['Vallée de Strangleronce' => 0, 'Nagrand' => 5]]);

    $map = $this->parser->getZoneExpansionMap();

    expect($map)->toHaveKey('vallée de strangleronce');
    expect($map['vallée de strangleronce'])->toBe(0);
    expect($map['nagrand'])->toBe(5);
});

test('getZoneExpansionMap does not override existing zones with supplementary', function (): void {
    writeQuestsJson([
        '10' => ['zones' => ['Nagrand' => ['ids' => [1]]]],
    ]);
    config(['wow_zones' => ['Nagrand' => 5]]);

    $map = $this->parser->getZoneExpansionMap();

    expect($map['nagrand'])->toBe(10); // JSON takes priority
});

test('getZoneExpansionMap returns empty when no data', function (): void {
    config(['wow_zones' => []]);

    $map = $this->parser->getZoneExpansionMap();

    expect($map)->toBe([]);
});

// ─── parseAllAddons ─────────────────────────────────────────

test('parseAllAddons normalizes quests from JSON', function (): void {
    writeQuestsJson([
        '0' => ['zones' => ['Durotar' => ['ids' => [1, 2]]]],
        '10' => ['zones' => ['Hallowfall' => ['ids' => [100]]]],
    ]);

    $result = $this->parser->parseAllAddons();

    expect($result['quests'])->toHaveCount(3);
    expect($result['quests'][0])->toBe(['id' => 1, 'expansion_id' => 0, 'zone_name' => 'Durotar']);
    expect($result['quests'][2])->toBe(['id' => 100, 'expansion_id' => 10, 'zone_name' => 'Hallowfall']);
});

test('parseAllAddons normalizes achievements from JSON', function (): void {
    writeAchievementsJson([
        '0' => ['total_ids' => [], 'categories' => ['Quêtes' => ['ids' => [10, 20]]]],
        '7' => ['total_ids' => [], 'categories' => ['Exploration' => ['ids' => [300]]]],
    ]);

    $result = $this->parser->parseAllAddons();

    expect($result['achievements'])->toHaveCount(3);
    expect($result['achievements'][0])->toBe(['id' => 10, 'expansion_id' => 0, 'category_name' => 'Quêtes']);
    expect($result['achievements'][2])->toBe(['id' => 300, 'expansion_id' => 7, 'category_name' => 'Exploration']);
});

test('parseAllAddons returns empty arrays when files missing', function (): void {
    $result = $this->parser->parseAllAddons();

    expect($result['quests'])->toBe([]);
    expect($result['achievements'])->toBe([]);
});

test('parseAllAddons skips expansions without zones or categories', function (): void {
    writeQuestsJson([
        '0' => ['total_ids' => [1, 2]], // No 'zones' key
    ]);
    writeAchievementsJson([
        '0' => ['total_ids' => [10]], // No 'categories' key
    ]);

    $result = $this->parser->parseAllAddons();

    expect($result['quests'])->toBe([]);
    expect($result['achievements'])->toBe([]);
});

// ─── getAllQuestIds / getAllAchievementIds ───────────────────

test('getAllQuestIds returns unique quest IDs', function (): void {
    writeQuestsJson([
        '0' => ['zones' => ['Durotar' => ['ids' => [1, 2, 3]]]],
        '10' => ['zones' => ['Dornogal' => ['ids' => [3, 4]]]],
    ]);

    $ids = $this->parser->getAllQuestIds();

    expect($ids)->toHaveCount(4);
    expect($ids)->toContain(1, 2, 3, 4);
});

test('getAllAchievementIds returns unique achievement IDs', function (): void {
    writeAchievementsJson([
        '0' => ['total_ids' => [], 'categories' => ['Quêtes' => ['ids' => [10, 20]]]],
        '7' => ['total_ids' => [], 'categories' => ['Quêtes' => ['ids' => [20, 30]]]],
    ]);

    $ids = $this->parser->getAllAchievementIds();

    expect($ids)->toHaveCount(3);
    expect($ids)->toContain(10, 20, 30);
});

// ─── getReputationFactionMap edge cases ─────────────────────

test('getReputationFactionMap returns empty when file missing', function (): void {
    expect($this->parser->getReputationFactionMap())->toBe([]);
});

test('getReputationFactionMap handles both negative bases as neutral', function (): void {
    writeFactionCsvDirect([
        ['500', '-1', '-1'], // Both negative → null
    ]);

    expect($this->parser->getReputationFactionMap())->toBe([]);
});

// ─── getQuestExpansionMap with BTW data ─────────────────────

test('getQuestExpansionMap includes BTW quest ContentTuning data', function (): void {
    $btwDir = storage_path('app/blizzard/mappings/BTW/BtWQuestsTheWarWithin/Database');
    mkdir($btwDir, 0755, true);

    file_put_contents($btwDir.'/Quests.lua', <<<'LUA'
    [80000] = {
        contentTuningID = 2797,
        name = "Test Quest",
    },
    [80001] = {
        contentTuningID = 2798,
        name = "Another Quest",
    },
    LUA);

    writeContentTuningCsvDirect([
        ['2797', '10'],
        ['2798', '11'],
    ]);

    $map = $this->parser->getQuestExpansionMap();

    expect($map[80000])->toBe(10);
    expect($map[80001])->toBe(11);
});

test('getQuestExpansionMap merges BTW and CSV data with BTW priority', function (): void {
    $btwDir = storage_path('app/blizzard/mappings/BTW/BtWQuestsTheWarWithin/Database');
    mkdir($btwDir, 0755, true);

    file_put_contents($btwDir.'/Quests.lua', <<<'LUA'
    [90000] = {
        contentTuningID = 2797,
    },
    LUA);

    writeContentTuningCsvDirect([
        ['2797', '10'],
        ['2798', '11'],
    ]);

    writeQuestV2CliTaskCsvDirect([
        ['90000', '2798'], // CSV says CT 2798, but BTW already has 2797
        ['90001', '2798'], // Only in CSV
    ]);

    $map = $this->parser->getQuestExpansionMap();

    expect($map[90000])->toBe(10); // BTW CT 2797 → exp 10
    expect($map[90001])->toBe(11); // CSV CT 2798 → exp 11
});

test('getQuestExpansionMap returns empty when no BTW dir and no CSV', function (): void {
    writeContentTuningCsvDirect([['100', '10']]);

    $map = $this->parser->getQuestExpansionMap();

    expect($map)->toBe([]);
});

// ─── BTW index file alternate paths ─────────────────────────

test('parseBtwZoneNames finds Index.frFR.lua at root level', function (): void {
    $btwDir = storage_path('app/blizzard/mappings/BTW/BtWQuestsClassic');
    mkdir($btwDir, 0755, true);

    file_put_contents($btwDir.'/Index.frFR.lua', <<<'LUA'
    {
        name = "Forêt des Pins Argentés",
        type = "category",
    },
    LUA);

    $map = $this->parser->getZoneExpansionMap();

    expect($map)->toHaveKey('forêt des pins argentés');
    expect($map['forêt des pins argentés'])->toBe(0);
});

// ─── Helpers ────────────────────────────────────────────────

function writeQuestsJson(array $data): void
{
    $dir = storage_path('app/blizzard/mappings/processed');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($dir.'/quests.json', json_encode($data));
}

function writeAchievementsJson(array $data): void
{
    $dir = storage_path('app/blizzard/mappings/processed');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($dir.'/achievements.json', json_encode($data));
}

function writeFactionCsvDirect(array $rows): void
{
    $lines = ['ID,ReputationBase_0,ReputationBase_1'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    file_put_contents(storage_path('app/blizzard/faction.csv'), implode("\n", $lines));
}

function writeContentTuningCsvDirect(array $rows): void
{
    $lines = ['ID,ExpansionID'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    file_put_contents(storage_path('app/blizzard/content_tuning.csv'), implode("\n", $lines));
}

function writeQuestV2CliTaskCsvDirect(array $rows): void
{
    $lines = ['ID,ContentTuningID'];
    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    file_put_contents(storage_path('app/blizzard/quest_v2_cli_task.csv'), implode("\n", $lines));
}
