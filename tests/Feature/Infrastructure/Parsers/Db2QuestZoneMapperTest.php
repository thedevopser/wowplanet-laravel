<?php

declare(strict_types=1);

use App\Infrastructure\Parsers\Db2QuestZoneMapper;

beforeEach(function (): void {
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

test('it maps quests to zones via QuestPOIBlob and UiMap', function (): void {
    // UiMap: 10 = sub-zone (type 4), parent 5
    //        5  = zone (type 3) "Hurlevent"
    // QuestPOIBlob: quest 1001 → UiMapID 10 (ObjectiveIndex -1)
    questZoneWriteUiMapCsv([
        ['5', 'Hurlevent', '3', '1'],
        ['10', 'Quartier des Mages', '4', '5'],
    ]);

    questZoneWriteQuestPoiBlobCsv([
        ['1001', '10', '-1'],
    ]);

    $result = Db2QuestZoneMapper::build();

    expect($result)->toHaveKey(1001)
        ->and($result[1001])->toBe('Hurlevent');
});

test('it returns empty map when CSV files are missing', function (): void {
    $result = Db2QuestZoneMapper::build();

    expect($result)->toBe([]);
});

// ─── Helpers ─────────────────────────────────────────────────

/**
 * Write ui_map.csv fixture.
 * $row = [ID, Name_lang, Type, ParentUiMapID]
 *
 * @param  list<array{0: string, 1: string, 2: string, 3: string}>  $rows
 */
function questZoneWriteUiMapCsv(array $rows): void
{
    $lines = ['ID,Name_lang,Type,ParentUiMapID,Flags,System,BountySetID,BountyDisplayLocation,VisibilityPlayerConditionID_0,VisibilityPlayerConditionID_1,ContentTuningID,HelpTextPosition,BkgAtlasID,AlternateUiMapGroup,ContentTuningConditionID'];
    foreach ($rows as $row) {
        $lines[] = sprintf(
            '%s,"%s",%s,%s,0,0,0,0,0,0,0,0,0,0,0',
            $row[0],
            $row[1],
            $row[2],
            $row[3],
        );
    }

    file_put_contents(storage_path('app/blizzard/ui_map.csv'), implode("\n", $lines));
}

/**
 * Write quest_poi_blob.csv fixture.
 * $row = [QuestID, UiMapID, ObjectiveIndex]
 *
 * @param  list<array{0: string, 1: string, 2: string}>  $rows
 */
function questZoneWriteQuestPoiBlobCsv(array $rows): void
{
    $lines = ['ID,QuestID,ObjectiveIndex,UiMapID,PlayerConditionID,NavigationPlayerConditionID,NumPoints,Flags,BlobIndex'];
    foreach ($rows as $row) {
        $lines[] = sprintf(
            '0,%s,%s,%s,0,0,0,0,0',
            $row[0],
            $row[2],
            $row[1],
        );
    }

    file_put_contents(storage_path('app/blizzard/quest_poi_blob.csv'), implode("\n", $lines));
}
