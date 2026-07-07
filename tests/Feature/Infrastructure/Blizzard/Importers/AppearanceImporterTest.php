<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Importers\AppearanceImporter;
use App\Models\WowAppearance;

beforeEach(function (): void {
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

test('it imports appearances joining DB2 CSVs and picks the highest-quality named item', function (): void {
    writeItemAppearanceCsv([
        [321, 132759],
        [500, 135274],
    ]);
    writeItemModifiedAppearanceCsv([
        // [ItemAppearanceID, ItemID]
        [321, 11735],
        [321, 19945],
        [500, 30000],
    ]);
    writeItemSparseCsv([
        // [ID, Display_lang, ExpansionID, InventoryType, OverallQualityID]
        [11735, 'Couvre-œil du forcené', 0, 1, 3],
        [19945, 'Couvre-œil en écailles de lézard', 0, 1, 4],
        [30000, 'Lame de test', 8, 13, 4],
    ]);
    writeManifestInterfaceDataCsv([
        // [FileDataID, FileName]
        [132759, 'INV_Chest_Samurai.blp'],
    ]);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->count())->toBe(2);

    $head = WowAppearance::query()->find(321);
    // item représentatif = meilleure qualité parmi les items nommés
    expect($head->name_fr)->toBe('Couvre-œil en écailles de lézard');
    expect($head->item_id)->toBe(19945);
    expect($head->quality)->toBe(4);
    expect($head->slot)->toBe('HEAD');
    expect($head->category)->toBe('Armure');
    expect($head->icon_file_data_id)->toBe(132759);
    expect($head->icon_url)->toBe('https://wow.zamimg.com/images/wow/icons/medium/inv_chest_samurai.jpg');
    expect($head->is_active)->toBeTrue();

    $weapon = WowAppearance::query()->find(500);
    expect($weapon->slot)->toBe('WEAPON');
    expect($weapon->category)->toBe('Arme');
    expect($weapon->expansion_id)->toBe(8);
});

test('it returns early when ItemAppearance CSV is empty', function (): void {
    writeItemAppearanceCsv([]);
    writeItemModifiedAppearanceCsv([]);
    writeItemSparseCsv([]);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->count())->toBe(0);
});

test('it marks appearances with only non-transmoggable items as inactive', function (): void {
    writeItemAppearanceCsv([[999, 100]]);
    writeItemModifiedAppearanceCsv([[999, 8000]]);
    writeItemSparseCsv([
        [8000, 'Collier scintillant', 0, 2, 2], // InventoryType 2 = NECK, non transmoggable
    ]);

    resolve(AppearanceImporter::class)->import();

    $appearance = WowAppearance::query()->find(999);
    expect($appearance->name_fr)->toBe('Collier scintillant');
    expect($appearance->slot)->toBeNull();
    expect($appearance->is_active)->toBeFalse();
});

test('it ignores placeholder/template items and prefers the real localized name', function (): void {
    writeItemAppearanceCsv([
        [700, 111],
        [701, 222],
    ]);
    writeItemModifiedAppearanceCsv([
        // appearance 700 : un vrai item FR + un template anglais
        [700, 10],
        [700, 11],
        // appearance 701 : uniquement des placeholders → doit devenir inactive
        [701, 20],
        [701, 21],
    ]);
    writeItemSparseCsv([
        [10, 'Heaume du gladiateur', 0, 1, 4],
        [11, '11.0 Raid Template - Helm', 0, 1, 4],
        [20, '10.0 Rare Reward TBD - Mace2H', 0, 13, 4],
        [21, 'Axe1H_Titan_C_01', 0, 13, 4],
    ]);

    resolve(AppearanceImporter::class)->import();

    $real = WowAppearance::query()->find(700);
    expect($real->name_fr)->toBe('Heaume du gladiateur')
        ->and($real->is_active)->toBeTrue();

    $placeholderOnly = WowAppearance::query()->find(701);
    expect($placeholderOnly->is_active)->toBeFalse()
        ->and($placeholderOnly->name_fr)->toStartWith('[EN]');
});

test('it uses a fallback name and stays inactive when no linked item has a name', function (): void {
    writeItemAppearanceCsv([[888, 0]]);
    writeItemModifiedAppearanceCsv([[888, 77777]]); // item absent d'ItemSparse
    writeItemSparseCsv([]);

    resolve(AppearanceImporter::class)->import();

    $appearance = WowAppearance::query()->find(888);
    expect($appearance->name_fr)->toStartWith('[EN]');
    expect($appearance->is_active)->toBeFalse();
});

// ─── Helpers ────────────────────────────────────────────────

function writeItemAppearanceCsv(array $rows): void
{
    $lines = ['ID,DisplayType,ItemDisplayInfoID,DefaultIconFileDataID,UiOrder,TransmogPlayerConditionID'];
    foreach ($rows as [$id, $iconFdid]) {
        $lines[] = sprintf('%d,0,0,%d,0,0', $id, $iconFdid);
    }

    file_put_contents(storage_path('app/blizzard/item_appearance.csv'), implode("\n", $lines));
}

function writeItemModifiedAppearanceCsv(array $rows): void
{
    $lines = ['ID,ItemID,ItemAppearanceModifierID,ItemAppearanceID,OrderIndex,TransmogSourceTypeEnum,Flags'];
    $rowId = 1;
    foreach ($rows as [$appearanceId, $itemId]) {
        $lines[] = sprintf('%d,%d,0,%d,0,0,0', $rowId++, $itemId, $appearanceId);
    }

    file_put_contents(storage_path('app/blizzard/item_modified_appearance.csv'), implode("\n", $lines));
}

function writeItemSparseCsv(array $rows): void
{
    $lines = ['ID,Display_lang,ExpansionID,InventoryType,OverallQualityID'];
    foreach ($rows as [$id, $name, $expansion, $inventoryType, $quality]) {
        $escaped = str_replace('"', '""', (string) $name);
        $lines[] = sprintf('%d,"%s",%d,%d,%d', $id, $escaped, $expansion, $inventoryType, $quality);
    }

    file_put_contents(storage_path('app/blizzard/item_sparse.csv'), implode("\n", $lines));
}

function writeManifestInterfaceDataCsv(array $rows): void
{
    $lines = ['ID,FilePath,FileName'];
    foreach ($rows as [$id, $fileName]) {
        $lines[] = sprintf('%d,Interface\ICONS\,%s', $id, $fileName);
    }

    file_put_contents(storage_path('app/blizzard/manifest_interface_data.csv'), implode("\n", $lines));
}
