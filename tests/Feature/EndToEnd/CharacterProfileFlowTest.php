<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Models\WowAchievement;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowQuest;

test('full character profile flow', function (): void {
    WowQuest::factory()->create([
        'id' => 100,
        'name_fr' => 'La menace Murloc',
        'expansion_id' => 0,
        'zone_name' => 'Forêt d\'Elwynn',
    ]);
    WowQuest::factory()->create([
        'id' => 101,
        'name_fr' => 'Patte de loup',
        'expansion_id' => 0,
        'zone_name' => 'Forêt d\'Elwynn',
    ]);

    WowAchievement::factory()->create([
        'id' => 200,
        'name_fr' => 'Niveau 10',
        'expansion_id' => 0,
        'category_name' => 'Général',
    ]);

    WowMount::factory()->create(['id' => 300, 'name_fr' => 'Loup noir']);
    WowMount::factory()->create(['id' => 301, 'name_fr' => 'Destrier']);
    WowPet::factory()->create(['id' => 400, 'name_fr' => 'Dragonnet']);
    WowDecor::factory()->create(['id' => 500, 'name_fr' => 'Foyer orné']);
    WowDecor::factory()->create(['id' => 501, 'name_fr' => 'Tapis elfique']);

    $mock = $this->mock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('get');
    $exp->andReturnUsing(function (string $endpoint): array {
        if (str_contains($endpoint, 'quests/completed')) {
            return ['quests' => [['id' => 100]]];
        }

        if (str_contains($endpoint, 'achievements')) {
            return ['achievements' => [['id' => 200]]];
        }

        if (str_contains($endpoint, 'collections/mounts')) {
            return ['mounts' => [['mount' => ['id' => 300]]]];
        }

        if (str_contains($endpoint, 'collections/pets')) {
            return ['pets' => [['species' => ['id' => 400]]]];
        }

        if (str_contains($endpoint, 'collections/decor')) {
            return ['decor_collected' => [['decor' => ['id' => 500]]]];
        }

        if (str_contains($endpoint, 'character-media')) {
            return [
                'assets' => [
                    ['key' => 'avatar', 'value' => 'https://render.com/avatar.jpg'],
                    ['key' => 'inset', 'value' => 'https://render.com/inset.jpg'],
                ],
            ];
        }

        if (str_contains($endpoint, 'playable-class')) {
            return ['assets' => [['key' => 'icon', 'value' => 'https://render.com/icon.jpg']]];
        }

        if (str_contains($endpoint, '/professions')) {
            return ['primaries' => [], 'secondaries' => []];
        }

        return [
            'name' => 'Thrall',
            'realm' => ['name' => 'Hyjal'],
            'race' => ['name' => 'Orc'],
            'character_class' => ['id' => 7, 'name' => 'Chaman'],
            'level' => 80,
            'equipped_item_level' => 620,
            'faction' => ['name' => 'Horde'],
        ];
    });

    $testResponse = $this->getJson('/api/character/hyjal/thrall');

    $testResponse->assertOk();

    /** @var array<string, mixed> $data */
    $data = $testResponse->json();

    expect($data['name'])->toBe('Thrall')
        ->and($data['realm'])->toBe('Hyjal')
        ->and($data['level'])->toBe(80)
        ->and($data['classId'])->toBe(7)
        ->and($data['mountsCount'])->toBe(1)
        ->and($data['petsCount'])->toBe(1)
        ->and($data['decorCount'])->toBe(1);

    /** @var list<array<string, mixed>> $decor */
    $decor = $data['decor'];
    expect($decor)->toHaveCount(2);

    /** @var array<int, array{quests: array{total: int, completed: int}, achievements: array{total: int, completed: int}}> $collections */
    $collections = $data['collections'];
    $classicCollections = $collections[0];
    expect($classicCollections['quests']['total'])->toBe(2)
        ->and($classicCollections['quests']['completed'])->toBe(1)
        ->and($classicCollections['achievements']['total'])->toBe(1)
        ->and($classicCollections['achievements']['completed'])->toBe(1);
});

test('character not found returns 404', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('get');
    $exp->andThrow(new Exception('Character not found'));

    $this->getJson('/api/character/hyjal/unknown')
        ->assertNotFound()
        ->assertJsonStructure(['error', 'message']);
});
