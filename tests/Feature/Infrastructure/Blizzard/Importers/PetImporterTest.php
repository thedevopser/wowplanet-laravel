<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\PetImporter;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;
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

/**
 * Range une mascotte dans la taxonomie curée.
 */
function curatePet(int $entryId, ?string $category, ?string $source): void
{
    WowCollectionTaxonomy::factory()->create([
        'entity' => CollectionEntity::Pet,
        'entry_id' => $entryId,
        'category' => $category,
        'source' => $source,
    ]);
}

test('it imports pets with French names from the API and the ranking from the taxonomy', function (): void {
    writePetsJson([
        [
            'name' => 'Ignorée, le rangement ne vient plus du fichier',
            'subcats' => [
                [
                    'name' => 'Ignorée aussi',
                    'items' => [
                        ['ID' => 300, 'name' => 'TestPet', 'icon' => 'pet_test', 'spellid' => 9876, 'creatureId' => 111, 'itemId' => null, 'faction' => null, 'quality' => 3],
                        ['ID' => 301, 'name' => 'OtherPet', 'icon' => 'pet_other', 'spellid' => 9877, 'creatureId' => 222, 'itemId' => null, 'faction' => null, 'quality' => 2],
                    ],
                ],
            ],
        ],
    ]);
    curatePet(300, 'Classic', 'Drop');
    curatePet(301, 'Legion', 'Quest');

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
    expect(WowPet::query()->find(301)->source)->toBe('Quest');
});

test('it keeps a pet the taxonomy does not rank, skips what the API index ignores, and deletes what dropped out', function (): void {
    WowPet::query()->create(['id' => 900, 'name_fr' => '[EN] Pet #900', 'is_active' => true]);

    writePetsJson([
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Drop',
                    'items' => [
                        ['ID' => 400, 'name' => 'LivePet', 'icon' => 'pet_x', 'spellid' => 5001, 'creatureId' => 333, 'itemId' => null, 'faction' => null, 'quality' => 3],
                        // Mascotte d'un patch à venir : dataminée, absente de l'index API live.
                        ['ID' => 5130, 'name' => 'UpcomingPet', 'icon' => 'pet_z', 'spellid' => 0, 'creatureId' => 0, 'itemId' => null, 'faction' => null, 'quality' => 3],
                    ],
                ],
            ],
        ],
    ]);
    curatePet(400, 'Classic', 'Drop');
    curatePet(5130, 'Midnight', 'Drop');

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    // 777 est dans l'index API mais la taxonomie ne la range pas : elle entre à arbitrer.
    mockPetIndex($client, [
        ['id' => 400, 'name' => 'Mascotte live'],
        ['id' => 777, 'name' => 'Mascotte non curée'],
    ]);

    resolve(PetImporter::class)->import();

    expect(WowPet::query()->count())->toBe(2);
    expect(WowPet::query()->find(400)->name_fr)->toBe('Mascotte live');
    expect(WowPet::query()->find(777)->name_fr)->toBe('Mascotte non curée');
    expect(WowPet::query()->find(777)->category)->toBeNull();
    expect(WowPet::query()->find(777)->creature_id)->toBeNull();
    expect(WowPet::query()->find(5130))->toBeNull();
    expect(WowPet::query()->find(900))->toBeNull();
});

test('it aborts without deleting anything when the pet index API call fails', function (): void {
    WowPet::query()->create(['id' => 500, 'name_fr' => 'Mascotte existante', 'is_active' => true]);

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
        ->and(WowPet::query()->find(500)->name_fr)->toBe('Mascotte existante');
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
