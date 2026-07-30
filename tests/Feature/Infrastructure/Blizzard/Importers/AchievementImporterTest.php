<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\AchievementImporter;
use App\Models\WowAchievement;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

/**
 * Mocke l'index API des hauts faits (source des noms français).
 *
 * @param  list<array{id: int, name: string}>  $achievements
 */
function mockAchievementIndex(\Mockery\MockInterface $mock, array $achievements): void
{
    $mock->shouldReceive('get')
        ->with('data/wow/achievement/index', \Mockery::any())
        ->andReturn([
            'achievements' => array_map(fn (array $achievement): array => ['id' => $achievement['id'], 'name' => $achievement['name']], $achievements),
        ]);
}

test('it imports achievements from SA JSON with French names from the API', function (): void {
    writeAchievementsJson([
        [
            'name' => 'General',
            'cats' => [
                [
                    'name' => 'Classic',
                    'subcats' => [
                        [
                            'name' => 'Exploration',
                            'items' => [
                                ['id' => 200, 'icon' => 'achievement_test', 'points' => 10],
                                ['id' => 201, 'icon' => 'achievement_other', 'points' => 5],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockAchievementIndex($client, [
        ['id' => 200, 'name' => 'Haut-fait Test'],
        ['id' => 201, 'name' => 'Haut-fait Autre'],
    ]);

    resolve(AchievementImporter::class)->import();

    expect(WowAchievement::query()->count())->toBe(2);
    expect(WowAchievement::query()->find(200)->name_fr)->toBe('Haut-fait Test');
    expect(WowAchievement::query()->find(200)->category_name)->toBe('General');
    expect(WowAchievement::query()->find(200)->expansion_id)->toBe(0);
    expect(WowAchievement::query()->find(200)->points)->toBe(10);
    expect(WowAchievement::query()->find(200)->icon_url)->toBe('https://wow.zamimg.com/images/wow/icons/medium/achievement_test.jpg');
    expect(WowAchievement::query()->find(200)->is_active)->toBeTrue();
    expect(WowAchievement::query()->find(201)->name_fr)->toBe('Haut-fait Autre');
    expect(WowAchievement::query()->find(201)->points)->toBe(5);
});

test('it returns early when SA JSON is empty', function (): void {
    writeAchievementsJson([]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldNotReceive('get');

    resolve(AchievementImporter::class)->import();

    expect(WowAchievement::query()->count())->toBe(0);
});

test('it uses fallback expansion_id 0 when SA has null expansion', function (): void {
    writeAchievementsJson([
        [
            'name' => 'General',
            'cats' => [
                [
                    'name' => 'UnknownExpansion',
                    'subcats' => [
                        [
                            'name' => 'SubCategory',
                            'items' => [
                                ['id' => 300, 'icon' => 'achievement_fallback', 'points' => 15],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockAchievementIndex($client, [
        ['id' => 300, 'name' => 'Haut-fait inconnu'],
    ]);

    resolve(AchievementImporter::class)->import();

    expect(WowAchievement::query()->count())->toBe(1);
    expect(WowAchievement::query()->find(300)->expansion_id)->toBe(0);
});

test('it skips achievements absent from either source and deletes those dropped from the catalog', function (): void {
    WowAchievement::query()->create(['id' => 900, 'name_fr' => '[EN] Midnight Seasonal', 'expansion_id' => 0, 'category_name' => 'Feats of Strength', 'points' => 0, 'is_active' => true]);

    writeAchievementsJson([
        [
            'name' => 'General',
            'cats' => [
                [
                    'name' => 'Classic',
                    'subcats' => [
                        [
                            'name' => 'Exploration',
                            'items' => [
                                ['id' => 400, 'icon' => 'achievement_x', 'points' => 10],
                                // Haut-fait d'une saison à venir : dataminé, absent de l'index API live.
                                ['id' => 5001, 'icon' => 'achievement_y', 'points' => 10],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockAchievementIndex($client, [
        ['id' => 400, 'name' => 'Haut-fait live'],
        ['id' => 777, 'name' => 'Haut-fait non curé'],
    ]);

    resolve(AchievementImporter::class)->import();

    expect(WowAchievement::query()->count())->toBe(1);
    expect(WowAchievement::query()->find(400)->name_fr)->toBe('Haut-fait live');
    expect(WowAchievement::query()->find(5001))->toBeNull();
    expect(WowAchievement::query()->find(777))->toBeNull();
    expect(WowAchievement::query()->find(900))->toBeNull();
});

test('it aborts without deleting anything when the achievement index API call fails', function (): void {
    WowAchievement::query()->create(['id' => 400, 'name_fr' => 'Haut-fait existant', 'expansion_id' => 0, 'category_name' => 'Classic', 'points' => 10, 'is_active' => true]);

    writeAchievementsJson([
        [
            'name' => 'General',
            'cats' => [
                [
                    'name' => 'Classic',
                    'subcats' => [
                        [
                            'name' => 'Exploration',
                            'items' => [
                                ['id' => 400, 'icon' => 'achievement_x', 'points' => 10],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('get')
        ->with('data/wow/achievement/index', \Mockery::any())
        ->andThrow(new \Exception('API error: 500 Internal Server Error'));

    resolve(AchievementImporter::class)->import();

    expect(WowAchievement::query()->count())->toBe(1)
        ->and(WowAchievement::query()->find(400)->name_fr)->toBe('Haut-fait existant');
});

// ─── Helpers ────────────────────────────────────────────────

function writeAchievementsJson(array $supercats): void
{
    $json = json_encode(['supercats' => $supercats], JSON_THROW_ON_ERROR);
    file_put_contents(storage_path('app/blizzard/achievements.json'), $json);
}
