<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\PetImporter;
use App\Models\WowPet;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

/**
 * Mocke l'index API des mascottes (source des noms français, id = species id).
 *
 * @param  list<array{id: int, name: string}>  $pets
 */
function mockPetIndex(\Mockery\MockInterface $mock, array $pets): void
{
    $mock->shouldReceive('get')
        ->with('data/wow/pet/index', \Mockery::any())
        ->andReturn([
            'pets' => array_map(fn (array $pet): array => ['id' => $pet['id'], 'name' => $pet['name']], $pets),
        ]);
}

test('it imports pets from SA JSON with French names from the API', function (): void {
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

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockPetIndex($client, [
        ['id' => 300, 'name' => 'Dragonnet'],
        ['id' => 301, 'name' => 'Petit chat'],
    ]);

    resolve(PetImporter::class)->import();

    expect(WowPet::query()->count())->toBe(2);
    expect(WowPet::query()->find(300)->name_fr)->toBe('Dragonnet');
    expect(WowPet::query()->find(300)->creature_id)->toBe(111);
    expect(WowPet::query()->find(300)->category)->toBe('Classic');
    expect(WowPet::query()->find(300)->source)->toBe('Drop');
    expect(WowPet::query()->find(300)->icon_url)->toBe('https://wow.zamimg.com/images/wow/icons/medium/pet_test.jpg');
    expect(WowPet::query()->find(300)->is_active)->toBeTrue();
    expect(WowPet::query()->find(301)->name_fr)->toBe('Petit chat');
});

test('it uses fallback name when the API has no French name', function (): void {
    writePetsJson([
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Drop',
                    'items' => [
                        ['ID' => 400, 'name' => 'UnknownPet', 'icon' => 'pet_x', 'spellid' => 5001, 'creatureId' => 333, 'itemId' => null, 'faction' => null, 'quality' => 3],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockPetIndex($client, []);

    resolve(PetImporter::class)->import();

    expect(WowPet::query()->count())->toBe(1);
    expect(WowPet::query()->find(400)->name_fr)->toStartWith('[EN]');
});

test('it still imports pets when the pet index API call fails', function (): void {
    writePetsJson([
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Drop',
                    'items' => [
                        ['ID' => 500, 'name' => 'ApiDownPet', 'icon' => 'pet_y', 'spellid' => 5002, 'creatureId' => 444, 'itemId' => null, 'faction' => null, 'quality' => 3],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('get')
        ->with('data/wow/pet/index', \Mockery::any())
        ->andThrow(new \Exception('API error: 500 Internal Server Error'));

    resolve(PetImporter::class)->import();

    expect(WowPet::query()->count())->toBe(1)
        ->and(WowPet::query()->find(500)->name_fr)->toStartWith('[EN]');
});

test('it returns early when SA JSON is empty', function (): void {
    writePetsJson([]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldNotReceive('get');

    resolve(PetImporter::class)->import();

    expect(WowPet::query()->count())->toBe(0);
});

// ─── Helpers ────────────────────────────────────────────────

function writePetsJson(array $categories): void
{
    $json = json_encode($categories, JSON_THROW_ON_ERROR);
    file_put_contents(storage_path('app/blizzard/pets.json'), $json);
}
