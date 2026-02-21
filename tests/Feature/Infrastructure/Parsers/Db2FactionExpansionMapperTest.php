<?php

declare(strict_types=1);

use App\Infrastructure\Parsers\Db2FactionExpansionMapper;

beforeEach(function (): void {
    $this->mapper = new Db2FactionExpansionMapper;

    $dir = storage_path('app/blizzard');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $this->backups = [];
    foreach (['faction.csv', 'currency_types.csv'] as $file) {
        $path = $dir.'/'.$file;
        if (file_exists($path)) {
            $backup = $path.'.testbak';
            rename($path, $backup);
            $this->backups[$path] = $backup;
        }
    }
});

afterEach(function (): void {
    foreach (['faction.csv', 'currency_types.csv'] as $file) {
        $path = storage_path('app/blizzard/'.$file);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    foreach ($this->backups as $original => $backup) {
        if (file_exists($backup)) {
            rename($backup, $original);
        }
    }
});

// ─── build() ────────────────────────────────────────────────

test('build returns empty when faction.csv is missing', function (): void {
    expect($this->mapper->build())->toBe([]);
});

test('build maps faction to expansion via direct parent', function (): void {
    factionWriteCsv([
        // Expansion header: Classic (1118)
        ['1118', 'Classique', '0', '-1'],
        // Faction under Classic
        ['72', 'Exodar', '1118', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->toHaveKey(72);
    expect($map[72])->toBe(0);
});

test('build maps faction via intermediate parent chain', function (): void {
    factionWriteCsv([
        // Expansion header: TBC (980)
        ['980', 'The Burning Crusade', '0', '-1'],
        // Sub-group under TBC
        ['936', 'Shattrath City', '980', '-1'],
        // Faction under sub-group
        ['1011', "Sha'tar", '936', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->toHaveKey(1011);
    expect($map[1011])->toBe(1);
});

test('build resolves multiple expansions', function (): void {
    factionWriteCsv([
        ['1118', 'Classique', '0', '-1'],
        ['980', 'The Burning Crusade', '0', '-1'],
        ['1097', 'Wrath of the Lich King', '0', '-1'],
        ['72', 'Hurlevent', '1118', '0'],
        ['1012', 'Ashtongue', '980', '0'],
        ['1037', 'Chevaliers de la Lame d\'ébène', '1097', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map[72])->toBe(0);
    expect($map[1012])->toBe(1);
    expect($map[1037])->toBe(2);
});

test('build excludes factions with ReputationIndex below 0', function (): void {
    factionWriteCsv([
        ['1118', 'Classique', '0', '-1'],
        ['72', 'Header Only', '1118', '-1'],
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(72);
});

test('build excludes factions with ParentFactionID 0', function (): void {
    factionWriteCsv([
        ['100', 'Orphan Faction', '0', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(100);
});

test('build excludes factions with parangon in name', function (): void {
    factionWriteCsv([
        ['1118', 'Classique', '0', '-1'],
        ['999', 'Hurlevent (parangon)', '1118', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(999);
});

test('build excludes DEPRECATED factions', function (): void {
    factionWriteCsv([
        ['1118', 'Classique', '0', '-1'],
        ['998', 'DEPRECATED Old Faction', '1118', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(998);
});

test('build excludes DNT factions', function (): void {
    factionWriteCsv([
        ['1118', 'Classique', '0', '-1'],
        ['997', '[DNT] Test Faction', '1118', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(997);
});

test('build excludes JOUEUR factions', function (): void {
    factionWriteCsv([
        ['1118', 'Classique', '0', '-1'],
        ['996', 'JOUEUR faction test', '1118', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(996);
});

test('build handles circular parent references without infinite loop', function (): void {
    factionWriteCsv([
        ['100', 'Faction A', '200', '0'],
        ['200', 'Faction B', '100', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(100);
    expect($map)->not->toHaveKey(200);
});

test('build skips factions whose parent chain does not reach an expansion header', function (): void {
    factionWriteCsv([
        ['500', 'Some Group', '0', '-1'],
        ['501', 'Orphan Faction', '500', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map)->not->toHaveKey(501);
});

test('build maps TWW expansion factions', function (): void {
    factionWriteCsv([
        ['2569', 'The War Within', '0', '-1'],
        ['2600', 'Council of Dornogal', '2569', '0'],
    ]);

    $map = $this->mapper->build();

    expect($map[2600])->toBe(10);
});

// ─── buildMaxRenownMap() ─────────────────────────────────────

test('buildMaxRenownMap returns empty when faction.csv is missing', function (): void {
    expect($this->mapper->buildMaxRenownMap())->toBe([]);
});

test('buildMaxRenownMap returns empty when currency_types.csv is missing', function (): void {
    renownFactionWriteCsv([
        ['2503', 'Centaure maruuk', '2506', '401', '2002'],
    ]);

    expect($this->mapper->buildMaxRenownMap())->toBe([]);
});

test('buildMaxRenownMap maps faction to max renown via currency', function (): void {
    renownFactionWriteCsv([
        ['2503', 'Centaure maruuk', '2506', '401', '2002'],
    ]);
    currencyWriteCsv([
        ['2002', 'Renown-Maruuk Centaur', '25'],
    ]);

    $map = $this->mapper->buildMaxRenownMap();

    expect($map)->toHaveKey(2503);
    expect($map[2503])->toBe(25);
});

test('buildMaxRenownMap handles multiple factions with different max renown', function (): void {
    renownFactionWriteCsv([
        ['2503', 'Centaure maruuk', '2506', '401', '2002'],
        ['2511', 'Rohart iskaarien', '2506', '402', '2087'],
        ['2590', 'Conseil de Dornogal', '2569', '442', '2900'],
    ]);
    currencyWriteCsv([
        ['2002', 'Renown-Maruuk', '25'],
        ['2087', 'Renown-Iskaara', '30'],
        ['2900', 'Renown-Dornogal', '25'],
    ]);

    $map = $this->mapper->buildMaxRenownMap();

    expect($map[2503])->toBe(25);
    expect($map[2511])->toBe(30);
    expect($map[2590])->toBe(25);
});

test('buildMaxRenownMap excludes factions without RenownCurrencyID', function (): void {
    renownFactionWriteCsv([
        ['72', 'Hurlevent', '1118', '0', '0'],
        ['2503', 'Centaure maruuk', '2506', '401', '2002'],
    ]);
    currencyWriteCsv([
        ['2002', 'Renown-Maruuk', '25'],
    ]);

    $map = $this->mapper->buildMaxRenownMap();

    expect($map)->not->toHaveKey(72);
    expect($map)->toHaveKey(2503);
});

test('buildMaxRenownMap excludes factions whose currency has no MaxQty', function (): void {
    renownFactionWriteCsv([
        ['2503', 'Centaure maruuk', '2506', '401', '9999'],
    ]);
    currencyWriteCsv([
        ['2002', 'Renown-Maruuk', '25'],
    ]);

    $map = $this->mapper->buildMaxRenownMap();

    expect($map)->not->toHaveKey(2503);
});

// ─── buildFactionNamesMap() ──────────────────────────────────

test('buildFactionNamesMap returns empty when faction.csv is missing', function (): void {
    expect($this->mapper->buildFactionNamesMap())->toBe([]);
});

test('buildFactionNamesMap returns names for valid factions', function (): void {
    factionWriteCsv([
        ['1118', 'Classique', '0', '-1'],
        ['72', 'Hurlevent', '1118', '0'],
        ['1037', 'Chevaliers', '1118', '0'],
    ]);

    $map = $this->mapper->buildFactionNamesMap();

    expect($map)->toHaveKey(72)
        ->and($map[72])->toBe('Hurlevent')
        ->and($map)->toHaveKey(1037)
        ->and($map[1037])->toBe('Chevaliers');
});

test('buildFactionNamesMap excludes factions with ReputationIndex below 0', function (): void {
    factionWriteCsv([
        ['1118', 'Classique', '0', '-1'],
    ]);

    $map = $this->mapper->buildFactionNamesMap();

    expect($map)->not->toHaveKey(1118);
});

test('buildFactionNamesMap excludes parangon factions', function (): void {
    factionWriteCsv([
        ['1118', 'Classique', '0', '-1'],
        ['999', 'Hurlevent (parangon)', '1118', '0'],
    ]);

    $map = $this->mapper->buildFactionNamesMap();

    expect($map)->not->toHaveKey(999);
});

test('buildFactionNamesMap excludes DEPRECATED factions', function (): void {
    factionWriteCsv([
        ['1118', 'Classique', '0', '-1'],
        ['998', 'DEPRECATED Old', '1118', '0'],
    ]);

    $map = $this->mapper->buildFactionNamesMap();

    expect($map)->not->toHaveKey(998);
});

// ─── Helpers ─────────────────────────────────────────────────

function factionWriteCsv(array $rows): void
{
    $lines = ['ID,ReputationIndex,ReputationRaceMask,Name_lang,Description_lang,ParentFactionID,ParentFactionMod_0,ParentFactionMod_1,ParentFactionCap_0,ParentFactionCap_1,ReputationFlags_0,ReputationFlags_1,ReputationFlags_2,ReputationFlags_3,ReputationBase_0,ReputationBase_1,ReputationBase_2,ReputationBase_3,ReputationMax_0,ReputationMax_1,ReputationMax_2,ReputationMax_3,ReputationClassMask_0,ReputationClassMask_1,ReputationClassMask_2,ReputationClassMask_3,Expansion'];
    foreach ($rows as $row) {
        // $row = [ID, Name_lang, ParentFactionID, ReputationIndex]
        $lines[] = sprintf(
            '"%s","%s","0","%s","","%s","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0"',
            $row[0],
            $row[3],
            $row[1],
            $row[2],
        );
    }

    file_put_contents(storage_path('app/blizzard/faction.csv'), implode("\n", $lines));
}

/**
 * Write a faction.csv with RenownCurrencyID column (full real header).
 * $row = [ID, Name_lang, ParentFactionID, ReputationIndex, RenownCurrencyID]
 */
function renownFactionWriteCsv(array $rows): void
{
    $lines = ['ID,ReputationRaceMask_0,ReputationRaceMask_1,ReputationRaceMask_2,ReputationRaceMask_3,Name_lang,Description_lang,ReputationIndex,ParentFactionID,Expansion,FriendshipRepID,Flags,ParagonFactionID,RenownFactionID,RenownCurrencyID,ReputationClassMask_0,ReputationClassMask_1,ReputationClassMask_2,ReputationClassMask_3,ReputationFlags_0,ReputationFlags_1,ReputationFlags_2,ReputationFlags_3,ReputationBase_0,ReputationBase_1,ReputationBase_2,ReputationBase_3,ReputationMax_0,ReputationMax_1,ReputationMax_2,ReputationMax_3,ParentFactionMod_0,ParentFactionMod_1,ParentFactionCap_0,ParentFactionCap_1'];
    foreach ($rows as $row) {
        // $row = [ID, Name_lang, ParentFactionID, ReputationIndex, RenownCurrencyID]
        $lines[] = sprintf(
            '%s,0,0,0,0,"%s","",%s,%s,0,0,0,0,0,%s,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0',
            $row[0],
            $row[1],
            $row[3],
            $row[2],
            $row[4],
        );
    }

    file_put_contents(storage_path('app/blizzard/faction.csv'), implode("\n", $lines));
}

/**
 * Write a currency_types.csv for testing.
 * $row = [ID, Name_lang, MaxQty]
 */
function currencyWriteCsv(array $rows): void
{
    $lines = ['ID,Name_lang,Description_lang,CategoryID,InventoryIconFileID,SpellWeight,SpellCategory,MaxQty,MaxEarnablePerWeek,Quality,FactionID,ItemGroupSoundsID,XpQuestDifficulty,AwardConditionID,MaxQtyWorldStateID,RechargingAmountPerCycle,RechargingCycleDurationMS,WarbondTransferPercentage,OrderIndex,RecraftReagentCountPercentage,OrderSource,MCRCurrencyID,Flags_0,Flags_1'];
    foreach ($rows as $row) {
        // $row = [ID, Name_lang, MaxQty]
        $lines[] = sprintf(
            '%s,"%s","",142,0,0,0,%s,0,6,0,0,0,0,0,0,0,0,0,0,0,0,0,0',
            $row[0],
            $row[1],
            $row[2],
        );
    }

    file_put_contents(storage_path('app/blizzard/currency_types.csv'), implode("\n", $lines));
}
