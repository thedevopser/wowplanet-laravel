<?php

declare(strict_types=1);

use App\Infrastructure\Parsers\Db2AreaExpansionMapper;

beforeEach(function (): void {
    setUpBlizzardTempStorage($this);
    $this->mapper = new Db2AreaExpansionMapper;
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

test('it builds area expansion map from CSV data', function (): void {
    // Area 100 on continent 571 (ExpansionID=2 in map.csv)
    // Area 200 on continent 870 (ExpansionID=4 in map.csv)
    areaWriteAreaTableCsv([
        ['100', '571', '0', '0'],
        ['200', '870', '0', '0'],
    ]);

    areaWriteMapCsv([
        ['571', '2'],
        ['870', '4'],
    ]);

    areaWriteContentTuningCsv([]);

    /** @var Db2AreaExpansionMapper $mapper */
    $mapper = $this->mapper;
    $result = $mapper->build();

    expect($result)->toHaveKey(100)
        ->and($result[100])->toBe(2)
        ->and($result)->toHaveKey(200)
        ->and($result[200])->toBe(4);
});

test('it uses content tuning expansion when available', function (): void {
    // Area 300 on continent 0 (Classic map, ExpansionID=0) but ContentTuningID=50
    // ContentTuning 50 has ExpansionID=7 (BfA)
    // When mapExp=0, it returns max(ctExp, 0) — so ctExp=7 wins
    areaWriteAreaTableCsv([
        ['300', '0', '0', '50'],
    ]);

    areaWriteMapCsv([
        ['0', '0'],
    ]);

    areaWriteContentTuningCsv([
        ['50', '7'],
    ]);

    /** @var Db2AreaExpansionMapper $mapper */
    $mapper = $this->mapper;
    $result = $mapper->build();

    expect($result)->toHaveKey(300)
        ->and($result[300])->toBe(7);
});

test('it applies manual overrides', function (): void {
    // Area 2037 is in AREA_EXPANSION_OVERRIDES → 1 (TBC)
    // Even though CSV says continent 0 (Classic), override should win
    areaWriteAreaTableCsv([
        ['2037', '0', '0', '0'],
    ]);

    areaWriteMapCsv([
        ['0', '0'],
    ]);

    areaWriteContentTuningCsv([]);

    /** @var Db2AreaExpansionMapper $mapper */
    $mapper = $this->mapper;
    $result = $mapper->build();

    expect($result)->toHaveKey(2037)
        ->and($result[2037])->toBe(1);
});

// ─── Helpers ─────────────────────────────────────────────────

/**
 * Write area_table.csv fixture.
 * $row = [ID, ContinentID, ParentAreaID, ContentTuningID]
 *
 * @param  list<array{0: string, 1: string, 2: string, 3: string}>  $rows
 */
function areaWriteAreaTableCsv(array $rows): void
{
    $lines = ['ID,ContinentID,ParentAreaID,ContentTuningID,AmbienceID,ZoneMusic,IntroSound,UwIntroSound,SoundProviderPref,SoundProviderPrefUnderwater,AreaName_lang,Flags_0,Flags_1'];
    foreach ($rows as $row) {
        $lines[] = sprintf(
            '%s,%s,%s,%s,0,0,0,0,0,0,"Zone %s",0,0',
            $row[0],
            $row[1],
            $row[2],
            $row[3],
            $row[0],
        );
    }

    file_put_contents(storage_path('app/blizzard/area_table.csv'), implode("\n", $lines));
}

/**
 * Write map.csv fixture.
 * $row = [ID, ExpansionID]
 *
 * @param  list<array{0: string, 1: string}>  $rows
 */
function areaWriteMapCsv(array $rows): void
{
    $lines = ['ID,Directory,MapName_lang,MapDescription0_lang,MapDescription1_lang,PvpShortDescription_lang,PvpLongDescription_lang,MapType,InstanceType,ExpansionID'];
    foreach ($rows as $row) {
        $lines[] = sprintf(
            '%s,"","Map %s","","","","",0,0,%s',
            $row[0],
            $row[0],
            $row[1],
        );
    }

    file_put_contents(storage_path('app/blizzard/map.csv'), implode("\n", $lines));
}

/**
 * Write content_tuning.csv fixture.
 * $row = [ID, ExpansionID]
 *
 * @param  list<array{0: string, 1: string}>  $rows
 */
function areaWriteContentTuningCsv(array $rows): void
{
    $lines = ['ID,MinLevel,MaxLevel,Flags,ExpansionID'];
    foreach ($rows as $row) {
        $lines[] = sprintf(
            '%s,1,70,0,%s',
            $row[0],
            $row[1],
        );
    }

    file_put_contents(storage_path('app/blizzard/content_tuning.csv'), implode("\n", $lines));
}
