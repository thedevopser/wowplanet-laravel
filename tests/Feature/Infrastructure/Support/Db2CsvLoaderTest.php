<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Support\Db2CsvLoader;

beforeEach(function (): void {
    $dir = storage_path('app/blizzard');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
});

afterEach(function (): void {
    foreach (['test_map.csv', 'test_headers.csv'] as $file) {
        $path = storage_path('app/blizzard/'.$file);
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

// ─── loadMap ───────────────────────────────────────────────

test('loadMap builds map from column indices', function (): void {
    file_put_contents(storage_path('app/blizzard/test_map.csv'), implode("\n", [
        'ID,Spell,CreatureID',
        '100,5001,200',
        '101,5002,201',
    ]));

    $map = Db2CsvLoader::loadMap('test_map.csv', 0, 1);

    expect($map)->toBe([100 => 5001, 101 => 5002]);
});

test('loadMap skips rows with zero keys or values', function (): void {
    file_put_contents(storage_path('app/blizzard/test_map.csv'), implode("\n", [
        'ID,Value',
        '0,100',   // Key is 0 → skipped
        '1,0',     // Value is 0 → skipped
        '2,200',   // Valid
    ]));

    $map = Db2CsvLoader::loadMap('test_map.csv', 0, 1);

    expect($map)->toBe([2 => 200]);
});

test('loadMap returns empty array when file missing', function (): void {
    expect(Db2CsvLoader::loadMap('nonexistent.csv', 0, 1))->toBe([]);
});

// ─── loadMapByHeaders ──────────────────────────────────────

test('loadMapByHeaders builds map from header names', function (): void {
    file_put_contents(storage_path('app/blizzard/test_headers.csv'), implode("\n", [
        'ID,ExpansionID,Name',
        '10,3,Cataclysm',
        '20,7,BfA',
    ]));

    $map = Db2CsvLoader::loadMapByHeaders('test_headers.csv', 'ID', 'ExpansionID');

    expect($map)->toBe([10 => 3, 20 => 7]);
});

test('loadMapByHeaders includes zero values', function (): void {
    file_put_contents(storage_path('app/blizzard/test_headers.csv'), implode("\n", [
        'ID,ExpansionID',
        '1,0',   // Classic (expansion 0) should be included
        '2,10',
    ]));

    $map = Db2CsvLoader::loadMapByHeaders('test_headers.csv', 'ID', 'ExpansionID');

    expect($map)->toBe([1 => 0, 2 => 10]);
});

test('loadMapByHeaders returns empty array when file missing', function (): void {
    expect(Db2CsvLoader::loadMapByHeaders('nonexistent.csv', 'ID', 'Value'))->toBe([]);
});
