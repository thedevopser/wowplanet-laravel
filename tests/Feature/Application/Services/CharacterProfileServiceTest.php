<?php

declare(strict_types=1);

use App\Application\Services\CharacterProfileService;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowQuest;

test('get profile returns correct dto', function (): void {
    WowQuest::factory()->create([
        'id' => 100,
        'name_fr' => 'Quête Test',
        'expansion_id' => 0,
        'zone_name' => 'Forêt d\'Elwynn',
        'is_active' => true,
    ]);

    WowMount::factory()->create([
        'id' => 200,
        'name_fr' => 'Loup noir',
        'is_active' => true,
    ]);

    WowPet::factory()->create([
        'id' => 300,
        'name_fr' => 'Dragonnet',
        'is_active' => true,
    ]);

    WowDecor::factory()->create([
        'id' => 500,
        'name_fr' => 'Foyer orné en pierre',
        'item_id' => 245000,
        'is_active' => true,
    ]);

    $mock = $this->mock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $mock->shouldReceive('get');
    $profileExp->with('profile/wow/character/hyjal/thrall')
        ->andReturn([
            'name' => 'Thrall',
            'realm' => ['name' => 'Hyjal'],
            'race' => ['name' => 'Orc'],
            'character_class' => ['id' => 7, 'name' => 'Chaman'],
            'level' => 80,
            'equipped_item_level' => 620,
            'faction' => ['name' => 'Horde'],
        ]);

    /** @var \Mockery\Expectation $mediaExp */
    $mediaExp = $mock->shouldReceive('get');
    $mediaExp->with('profile/wow/character/hyjal/thrall/character-media')
        ->andReturn([
            'assets' => [
                ['key' => 'avatar', 'value' => 'https://render.com/avatar.jpg'],
                ['key' => 'inset', 'value' => 'https://render.com/inset.jpg'],
            ],
        ]);

    /** @var \Mockery\Expectation $classMediaExp */
    $classMediaExp = $mock->shouldReceive('get');
    $classMediaExp->withArgs(fn (string $endpoint): bool => str_contains($endpoint, 'media/playable-class'))
        ->andReturn([
            'assets' => [
                ['key' => 'icon', 'value' => 'https://render.com/class-icon.jpg'],
            ],
        ]);

    /** @var \Mockery\Expectation $questsExp */
    $questsExp = $mock->shouldReceive('get');
    $questsExp->with('profile/wow/character/hyjal/thrall/quests/completed')
        ->andReturn([
            'quests' => [['id' => 100]],
        ]);

    /** @var \Mockery\Expectation $achievementsExp */
    $achievementsExp = $mock->shouldReceive('get');
    $achievementsExp->with('profile/wow/character/hyjal/thrall/achievements')
        ->andReturn([
            'achievements' => [],
        ]);

    /** @var \Mockery\Expectation $mountsExp */
    $mountsExp = $mock->shouldReceive('get');
    $mountsExp->with('profile/wow/character/hyjal/thrall/collections/mounts')
        ->andReturn([
            'mounts' => [['mount' => ['id' => 200]]],
        ]);

    /** @var \Mockery\Expectation $petsExp */
    $petsExp = $mock->shouldReceive('get');
    $petsExp->with('profile/wow/character/hyjal/thrall/collections/pets')
        ->andReturn([
            'pets' => [['species' => ['id' => 300]]],
        ]);

    /** @var \Mockery\Expectation $professionsExp */
    $professionsExp = $mock->shouldReceive('get');
    $professionsExp->with('profile/wow/character/hyjal/thrall/professions')
        ->andReturn([
            'primaries' => [],
            'secondaries' => [],
        ]);

    /** @var \Mockery\Expectation $reputationsExp */
    $reputationsExp = $mock->shouldReceive('get');
    $reputationsExp->with('profile/wow/character/hyjal/thrall/reputations')
        ->andReturn([
            'reputations' => [],
        ]);

    /** @var \Mockery\Expectation $decorExp */
    $decorExp = $mock->shouldReceive('get');
    $decorExp->with('profile/wow/character/hyjal/thrall/collections/decor')
        ->andReturn([
            'decor_collected' => [['decor' => ['id' => 500]]],
        ]);

    $characterProfileService = resolve(CharacterProfileService::class);
    $characterProfileDTO = $characterProfileService->getProfile('Hyjal', 'Thrall');

    expect($characterProfileDTO->name)->toBe('Thrall')
        ->and($characterProfileDTO->realm)->toBe('Hyjal')
        ->and($characterProfileDTO->level)->toBe(80)
        ->and($characterProfileDTO->classId)->toBe(7)
        ->and($characterProfileDTO->mountsCount)->toBe(1)
        ->and($characterProfileDTO->petsCount)->toBe(1)
        ->and($characterProfileDTO->decorCount)->toBe(1)
        ->and($characterProfileDTO->exaltedCount)->toBe(0)
        ->and($characterProfileDTO->classIconUrl)->toBe('https://render.com/class-icon.jpg')
        ->and($characterProfileDTO->professions)->toBe([])
        ->and($characterProfileDTO->decor)->toHaveCount(1)
        ->and($characterProfileDTO->decor[0]['is_completed'])->toBeTrue()
        ->and($characterProfileDTO->decor[0]['name'])->toBe('Foyer orné en pierre');
});

test('aggregate progress groups by expansion and zone', function (): void {
    WowQuest::factory()->create([
        'id' => 1,
        'name_fr' => 'Quête Classic',
        'expansion_id' => 0,
        'zone_name' => 'Durotar',
        'is_active' => true,
    ]);
    WowQuest::factory()->create([
        'id' => 2,
        'name_fr' => 'Quête Classic 2',
        'expansion_id' => 0,
        'zone_name' => 'Durotar',
        'is_active' => true,
    ]);
    WowQuest::factory()->create([
        'id' => 3,
        'name_fr' => 'Quête TWW',
        'expansion_id' => 10,
        'zone_name' => 'Khaz Algar',
        'is_active' => true,
    ]);

    $mock = $this->mock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('get');
    $exp->andReturnUsing(function (string $endpoint): array {
        if (str_contains($endpoint, 'quests/completed')) {
            return ['quests' => [['id' => 1]]];
        }

        if (str_contains($endpoint, 'achievements')) {
            return ['achievements' => []];
        }

        if (str_contains($endpoint, 'collections/mounts')) {
            return ['mounts' => []];
        }

        if (str_contains($endpoint, 'collections/pets')) {
            return ['pets' => []];
        }

        if (str_contains($endpoint, 'collections/decor')) {
            return ['decor_collected' => []];
        }

        if (str_contains($endpoint, 'character-media')) {
            return ['assets' => [['key' => 'avatar', 'value' => '']]];
        }

        if (str_contains($endpoint, 'playable-class')) {
            return ['assets' => []];
        }

        if (str_contains($endpoint, '/professions')) {
            return ['primaries' => [], 'secondaries' => []];
        }

        if (str_contains($endpoint, '/reputations')) {
            return ['reputations' => []];
        }

        return [
            'name' => 'Test',
            'realm' => ['name' => 'Test'],
            'race' => ['name' => 'Human'],
            'character_class' => ['id' => 1, 'name' => 'Warrior'],
            'level' => 80,
            'equipped_item_level' => 600,
            'faction' => ['name' => 'Alliance'],
        ];
    });

    $characterProfileService = resolve(CharacterProfileService::class);
    $characterProfileDTO = $characterProfileService->getProfile('test', 'test');

    /** @var array{quests: array{total: int, completed: int}, achievements: array{total: int, completed: int}} $classicData */
    $classicData = $characterProfileDTO->collections[0];
    expect($classicData['quests']['total'])->toBe(2)
        ->and($classicData['quests']['completed'])->toBe(1);

    /** @var array{quests: array{total: int, completed: int}, achievements: array{total: int, completed: int}} $twwData */
    $twwData = $characterProfileDTO->collections[10];
    expect($twwData['quests']['total'])->toBe(1)
        ->and($twwData['quests']['completed'])->toBe(0);
});

test('aggregate progress filters quests by character faction', function (): void {
    WowQuest::factory()->create([
        'id' => 1,
        'name_fr' => 'Quête neutre',
        'expansion_id' => 0,
        'zone_name' => 'Durotar',
        'faction' => null,
        'is_active' => true,
    ]);
    WowQuest::factory()->create([
        'id' => 2,
        'name_fr' => 'Quête Horde',
        'expansion_id' => 0,
        'zone_name' => 'Durotar',
        'faction' => 'Horde',
        'is_active' => true,
    ]);
    WowQuest::factory()->create([
        'id' => 3,
        'name_fr' => 'Quête Alliance',
        'expansion_id' => 0,
        'zone_name' => 'Durotar',
        'faction' => 'Alliance',
        'is_active' => true,
    ]);

    $mock = $this->mock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('get');
    $exp->andReturnUsing(function (string $endpoint): array {
        if (str_contains($endpoint, 'quests/completed')) {
            return ['quests' => [['id' => 1], ['id' => 2]]];
        }

        if (str_contains($endpoint, 'achievements')) {
            return ['achievements' => []];
        }

        if (str_contains($endpoint, 'collections/mounts')) {
            return ['mounts' => []];
        }

        if (str_contains($endpoint, 'collections/pets')) {
            return ['pets' => []];
        }

        if (str_contains($endpoint, 'collections/decor')) {
            return ['decor_collected' => []];
        }

        if (str_contains($endpoint, 'character-media')) {
            return ['assets' => [['key' => 'avatar', 'value' => '']]];
        }

        if (str_contains($endpoint, 'playable-class')) {
            return ['assets' => []];
        }

        if (str_contains($endpoint, '/professions')) {
            return ['primaries' => [], 'secondaries' => []];
        }

        if (str_contains($endpoint, '/reputations')) {
            return ['reputations' => []];
        }

        return [
            'name' => 'Thrall',
            'realm' => ['name' => 'Hyjal'],
            'race' => ['name' => 'Orc'],
            'character_class' => ['id' => 7, 'name' => 'Chaman'],
            'level' => 80,
            'equipped_item_level' => 600,
            'faction' => ['name' => 'Horde'],
        ];
    });

    $characterProfileService = resolve(CharacterProfileService::class);
    $characterProfileDTO = $characterProfileService->getProfile('hyjal', 'thrall');

    /** @var array{quests: array{total: int, completed: int}} $classicData */
    $classicData = $characterProfileDTO->collections[0];
    // Should see 2 quests (neutral + Horde), NOT the Alliance quest
    expect($classicData['quests']['total'])->toBe(2)
        ->and($classicData['quests']['completed'])->toBe(2);
});

test('get profile handles decor api 404 gracefully', function (): void {
    WowDecor::factory()->create([
        'id' => 500,
        'name_fr' => 'Foyer orné',
        'is_active' => true,
    ]);

    $mock = $this->mock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('get');
    $exp->andReturnUsing(function (string $endpoint): array {
        throw_if(str_contains($endpoint, 'collections/decor'), Exception::class, '404 Not Found');

        if (str_contains($endpoint, 'quests/completed')) {
            return ['quests' => []];
        }

        if (str_contains($endpoint, 'achievements')) {
            return ['achievements' => []];
        }

        if (str_contains($endpoint, 'collections/mounts')) {
            return ['mounts' => []];
        }

        if (str_contains($endpoint, 'collections/pets')) {
            return ['pets' => []];
        }

        if (str_contains($endpoint, 'character-media')) {
            return ['assets' => [['key' => 'avatar', 'value' => '']]];
        }

        if (str_contains($endpoint, 'playable-class')) {
            return ['assets' => []];
        }

        if (str_contains($endpoint, '/professions')) {
            return ['primaries' => [], 'secondaries' => []];
        }

        if (str_contains($endpoint, '/reputations')) {
            return ['reputations' => []];
        }

        return [
            'name' => 'Test',
            'realm' => ['name' => 'Test'],
            'race' => ['name' => 'Human'],
            'character_class' => ['id' => 1, 'name' => 'Warrior'],
            'level' => 80,
            'equipped_item_level' => 600,
            'faction' => ['name' => 'Alliance'],
        ];
    });

    $characterProfileService = resolve(CharacterProfileService::class);
    $characterProfileDTO = $characterProfileService->getProfile('test', 'test');

    // Decor API failed but profile still works — no decor collected
    expect($characterProfileDTO->decorCount)->toBe(0)
        ->and($characterProfileDTO->decor)->toHaveCount(1)
        ->and($characterProfileDTO->decor[0]['is_completed'])->toBeFalse();
});
