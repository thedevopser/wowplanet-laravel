<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\MountImporter;
use App\Models\WowMount;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

/**
 * Mocke l'index API des montures (source des noms français).
 *
 * @param  list<array{id: int, name: string}>  $mounts
 */
function mockMountIndex(\Mockery\MockInterface $mock, array $mounts): void
{
    $mock->shouldReceive('get')
        ->with('data/wow/mount/index', \Mockery::any())
        ->andReturn([
            'mounts' => array_map(fn (array $mount): array => ['id' => $mount['id'], 'name' => $mount['name']], $mounts),
        ]);
}

test('it imports mounts from SA JSON with French names from the API', function (): void {
    writeMountsJson([
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Reputation',
                    'items' => [
                        ['ID' => 100, 'name' => 'TestMount1', 'icon' => 'ability_mount_test', 'spellid' => 1234, 'creatureId' => 5678, 'itemId' => null, 'faction' => 'Alliance', 'quality' => 4],
                        ['ID' => 101, 'name' => 'TestMount2', 'icon' => 'ability_mount_horse', 'spellid' => 0, 'creatureId' => 0, 'itemId' => null, 'faction' => null, 'quality' => 3],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, [
        ['id' => 100, 'name' => 'Monture Test'],
        ['id' => 101, 'name' => 'Destrier squelette'],
    ]);

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->count())->toBe(2);
    expect(WowMount::query()->find(100)->name_fr)->toBe('Monture Test');
    expect(WowMount::query()->find(100)->category)->toBe('Classic');
    expect(WowMount::query()->find(100)->source)->toBe('Reputation');
    expect(WowMount::query()->find(100)->icon_url)->toBe('https://wow.zamimg.com/images/wow/icons/medium/ability_mount_test.jpg');
    expect(WowMount::query()->find(100)->source_spell_id)->toBe(1234);
    expect(WowMount::query()->find(100)->is_active)->toBeTrue();
    expect(WowMount::query()->find(101)->name_fr)->toBe('Destrier squelette');
});

test('it skips API mounts absent from the curated SA JSON', function (): void {
    writeMountsJson([
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Reputation',
                    'items' => [
                        ['ID' => 100, 'name' => 'TestMount1', 'icon' => 'ability_mount_test', 'spellid' => 1234, 'creatureId' => 5678, 'itemId' => null, 'faction' => null, 'quality' => 4],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    // 999 n'est pas dans SimpleArmory : entrée non curée (doublon, variante PNJ), écartée.
    mockMountIndex($client, [
        ['id' => 100, 'name' => 'Monture Test'],
        ['id' => 999, 'name' => 'Monture API seule'],
    ]);

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->count())->toBe(1);
    expect(WowMount::query()->find(100)->category)->toBe('Classic');
    expect(WowMount::query()->find(999))->toBeNull();
});

test('it skips SA mounts absent from the live API index', function (): void {
    writeMountsJson([
        [
            'name' => 'Midnight',
            'subcats' => [
                [
                    'name' => 'Achievement',
                    'items' => [
                        ['ID' => 100, 'name' => 'LiveMount', 'icon' => 'ability_mount_test', 'spellid' => 0, 'creatureId' => 0, 'itemId' => null, 'faction' => null, 'quality' => 4],
                        // Contenu daté d'un patch à venir : présent dans les DB2 dataminés, pas encore live.
                        ['ID' => 3021, 'name' => 'Crimson Venomfang', 'icon' => 'inv_venomserpentmount_pink', 'spellid' => 0, 'creatureId' => 0, 'itemId' => null, 'faction' => null, 'quality' => 4],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, [['id' => 100, 'name' => 'Monture live']]);

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->count())->toBe(1);
    expect(WowMount::query()->find(3021))->toBeNull();
});

test('it deletes mounts that dropped out of the catalog', function (): void {
    WowMount::query()->create(['id' => 3021, 'name_fr' => '[EN] Mount #3021', 'category' => 'Midnight', 'is_active' => true]);

    writeMountsJson([
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Reputation',
                    'items' => [
                        ['ID' => 100, 'name' => 'TestMount1', 'icon' => 'ability_mount_test', 'spellid' => 0, 'creatureId' => 0, 'itemId' => null, 'faction' => null, 'quality' => 4],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, [['id' => 100, 'name' => 'Monture Test']]);

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->find(3021))->toBeNull();
    expect(WowMount::query()->count())->toBe(1);
});

test('it returns early when SA JSON is empty', function (): void {
    writeMountsJson([]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldNotReceive('get');

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->count())->toBe(0);
});

test('it aborts without touching the catalog when the API index is empty', function (): void {
    WowMount::query()->create(['id' => 200, 'name_fr' => 'Monture existante', 'is_active' => true]);

    writeMountsJson([
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Drop',
                    'items' => [
                        ['ID' => 200, 'name' => 'EnglishMount', 'icon' => 'ability_mount_en', 'spellid' => 0, 'creatureId' => 0, 'itemId' => null, 'faction' => null, 'quality' => 3],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockMountIndex($client, []);

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->count())->toBe(1);
    expect(WowMount::query()->find(200)->name_fr)->toBe('Monture existante');
});

test('it aborts without deleting anything when the mount index API call fails', function (): void {
    WowMount::query()->create(['id' => 300, 'name_fr' => 'Monture existante', 'is_active' => true]);

    writeMountsJson([
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Drop',
                    'items' => [
                        ['ID' => 300, 'name' => 'ApiDownMount', 'icon' => 'ability_mount_x', 'spellid' => 0, 'creatureId' => 0, 'itemId' => null, 'faction' => null, 'quality' => 3],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('get')
        ->with('data/wow/mount/index', \Mockery::any())
        ->andThrow(new \Exception('API error: 500 Internal Server Error'));

    resolve(MountImporter::class)->import();

    expect(WowMount::query()->count())->toBe(1)
        ->and(WowMount::query()->find(300)->name_fr)->toBe('Monture existante');
});

// ─── Helpers ────────────────────────────────────────────────

function writeMountsJson(array $categories): void
{
    $json = json_encode($categories, JSON_THROW_ON_ERROR);
    file_put_contents(storage_path('app/blizzard/mounts.json'), $json);
}
