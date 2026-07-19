<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\DecorImporter;
use App\Models\WowDecor;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

/**
 * Mocke l'index API des décors (source des noms français).
 *
 * @param  list<array{id: int, name: string}>  $decors
 */
function mockDecorIndex(\Mockery\MockInterface $mock, array $decors): void
{
    $mock->shouldReceive('get')
        ->with('data/wow/decor/index', \Mockery::any())
        ->andReturn([
            'decor_items' => array_map(fn (array $decor): array => ['id' => $decor['id'], 'name' => $decor['name']], $decors),
        ]);
}

test('it imports decors from SA JSON with French names from the API', function (): void {
    writeDecorsJson([
        [
            'name' => 'The War Within',
            'subcats' => [
                [
                    'name' => 'Quest',
                    'items' => [
                        ['ID' => 400, 'name' => 'TestDecor', 'icon' => 'decor_test', 'spellid' => 0, 'creatureId' => 0, 'itemId' => '555', 'faction' => null, 'quality' => 1, 'notObtainable' => false],
                        ['ID' => 401, 'name' => 'OtherDecor', 'icon' => 'decor_other', 'spellid' => 0, 'creatureId' => 0, 'itemId' => '556', 'faction' => null, 'quality' => 1, 'notObtainable' => false],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockDecorIndex($client, [
        ['id' => 400, 'name' => 'Decoration Test'],
        ['id' => 401, 'name' => 'Tapis elfique'],
    ]);

    resolve(DecorImporter::class)->import();

    expect(WowDecor::query()->count())->toBe(2);
    expect(WowDecor::query()->find(400)->name_fr)->toBe('Decoration Test');
    expect(WowDecor::query()->find(400)->category)->toBe('The War Within');
    expect(WowDecor::query()->find(400)->source)->toBe('Quest');
    expect(WowDecor::query()->find(400)->item_id)->toBe(555);
    expect(WowDecor::query()->find(400)->is_active)->toBeTrue();
    expect(WowDecor::query()->find(401)->name_fr)->toBe('Tapis elfique');
});

test('it marks not obtainable decors as inactive', function (): void {
    writeDecorsJson([
        [
            'name' => 'Undiscovered',
            'subcats' => [
                [
                    'name' => 'Undiscovered Sources',
                    'items' => [
                        ['ID' => 500, 'name' => 'HiddenDecor', 'icon' => 'decor_hidden', 'spellid' => 0, 'creatureId' => 0, 'itemId' => '700', 'faction' => null, 'quality' => 1, 'notObtainable' => true],
                    ],
                ],
            ],
        ],
        [
            'name' => 'The War Within',
            'subcats' => [
                [
                    'name' => 'Quest',
                    'items' => [
                        ['ID' => 501, 'name' => 'VisibleDecor', 'icon' => 'decor_visible', 'spellid' => 0, 'creatureId' => 0, 'itemId' => '701', 'faction' => null, 'quality' => 1, 'notObtainable' => false],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockDecorIndex($client, [
        ['id' => 500, 'name' => 'Decor cache'],
        ['id' => 501, 'name' => 'Foyer orne'],
    ]);

    resolve(DecorImporter::class)->import();

    expect(WowDecor::query()->count())->toBe(2);
    expect(WowDecor::query()->find(500)->is_active)->toBeFalse();
    expect(WowDecor::query()->find(500)->category)->toBe('Undiscovered');
    expect(WowDecor::query()->find(501)->is_active)->toBeTrue();
});

test('it falls back to an English name when the API does not know the decor', function (): void {
    writeDecorsJson([
        [
            'name' => 'The War Within',
            'subcats' => [
                [
                    'name' => 'Quest',
                    'items' => [
                        ['ID' => 600, 'name' => 'UnknownDecor', 'icon' => 'decor_x', 'spellid' => 0, 'creatureId' => 0, 'itemId' => '800', 'faction' => null, 'quality' => 1, 'notObtainable' => false],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockDecorIndex($client, []);

    resolve(DecorImporter::class)->import();

    expect(WowDecor::query()->find(600)->name_fr)->toStartWith('[EN]');
});

test('it still imports decors when the decor index API call fails', function (): void {
    writeDecorsJson([
        [
            'name' => 'The War Within',
            'subcats' => [
                [
                    'name' => 'Quest',
                    'items' => [
                        ['ID' => 700, 'name' => 'ApiDownDecor', 'icon' => 'decor_y', 'spellid' => 0, 'creatureId' => 0, 'itemId' => '900', 'faction' => null, 'quality' => 1, 'notObtainable' => false],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('get')
        ->with('data/wow/decor/index', \Mockery::any())
        ->andThrow(new \Exception('API error: 500 Internal Server Error'));

    resolve(DecorImporter::class)->import();

    expect(WowDecor::query()->count())->toBe(1)
        ->and(WowDecor::query()->find(700)->name_fr)->toStartWith('[EN]');
});

test('it returns early when SA JSON is empty', function (): void {
    writeDecorsJson([]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldNotReceive('get');

    resolve(DecorImporter::class)->import();

    expect(WowDecor::query()->count())->toBe(0);
});

// ─── Helpers ────────────────────────────────────────────────

function writeDecorsJson(array $categories): void
{
    $json = json_encode($categories, JSON_THROW_ON_ERROR);
    file_put_contents(storage_path('app/blizzard/decors.json'), $json);
}
