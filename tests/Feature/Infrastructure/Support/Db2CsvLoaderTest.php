<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Support\Db2CsvLoader;

beforeEach(function (): void {
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
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
