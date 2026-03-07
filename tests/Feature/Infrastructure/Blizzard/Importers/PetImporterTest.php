<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Importers\PetImporter;
use App\Models\WowPet;

beforeEach(function (): void {
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

test('it imports pets from SA JSON and spell names', function (): void {
    writePetsJson([
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Drop',
                    'items' => [
                        ['ID' => 300, 'name' => 'TestPet', 'icon' => 'pet_test', 'spellid' => 9876, 'creatureId' => 111, 'itemId' => null, 'faction' => null, 'quality' => 3],
                        ['ID' => 301, 'name' => 'OtherPet', 'icon' => 'pet_other', 'spellid' => 9877, 'creatureId' => 222, 'itemId' => null, 'faction' => null, 'quality' => 2],
                    ],
                ],
            ],
        ],
    ]);
    writeBattlePetSpeciesCsv([
        [300, 9876],
        [301, 9877],
    ]);

    $spellNameMap = [
        9876 => 'Dragonnet',
        9877 => 'Petit chat',
    ];

    $petImporter = resolve(PetImporter::class);
    $petImporter->import($spellNameMap);

    expect(WowPet::query()->count())->toBe(2);
    expect(WowPet::query()->find(300)->name_fr)->toBe('Dragonnet');
    expect(WowPet::query()->find(300)->creature_id)->toBe(111);
    expect(WowPet::query()->find(300)->category)->toBe('Classic');
    expect(WowPet::query()->find(300)->source)->toBe('Drop');
    expect(WowPet::query()->find(300)->icon_url)->toBe('https://wow.zamimg.com/images/wow/icons/medium/pet_test.jpg');
    expect(WowPet::query()->find(300)->is_active)->toBeTrue();
    expect(WowPet::query()->find(301)->name_fr)->toBe('Petit chat');
});

test('it cleans French spell name prefixes', function (): void {
    writePetsJson([
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Drop',
                    'items' => [
                        ['ID' => 400, 'name' => 'PrefixedPet', 'icon' => 'pet_prefixed', 'spellid' => 5001, 'creatureId' => 333, 'itemId' => null, 'faction' => null, 'quality' => 3],
                        ['ID' => 401, 'name' => 'InvokerPet', 'icon' => 'pet_invoker', 'spellid' => 5002, 'creatureId' => 444, 'itemId' => null, 'faction' => null, 'quality' => 3],
                    ],
                ],
            ],
        ],
    ]);
    writeBattlePetSpeciesCsv([
        [400, 5001],
        [401, 5002],
    ]);

    $spellNameMap = [
        5001 => 'Invocation : MonPet',
        5002 => 'Invoquer Petit chat',
    ];

    $petImporter = resolve(PetImporter::class);
    $petImporter->import($spellNameMap);

    expect(WowPet::query()->count())->toBe(2);
    expect(WowPet::query()->find(400)->name_fr)->toBe('MonPet');
    expect(WowPet::query()->find(401)->name_fr)->toBe('Petit chat');
});

test('it returns early when SA JSON is empty', function (): void {
    writePetsJson([]);
    writeBattlePetSpeciesCsv([]);

    $petImporter = resolve(PetImporter::class);
    $petImporter->import([]);

    expect(WowPet::query()->count())->toBe(0);
});

// ─── Helpers ────────────────────────────────────────────────

function writePetsJson(array $categories): void
{
    $json = json_encode($categories, JSON_THROW_ON_ERROR);
    file_put_contents(storage_path('app/blizzard/pets.json'), $json);
}

function writeBattlePetSpeciesCsv(array $rows): void
{
    $lines = ['Description_lang,SourceText_lang,ID,CreatureID,SummonSpellID,IconFileDataID'];
    foreach ($rows as $row) {
        $lines[] = sprintf(',.,"%d","0","%d",0', $row[0], $row[1]);
    }

    file_put_contents(storage_path('app/blizzard/battle_pet_species.csv'), implode("\n", $lines));
}
