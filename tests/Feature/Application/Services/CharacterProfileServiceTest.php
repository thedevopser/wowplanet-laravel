<?php

declare(strict_types=1);

use App\Application\Services\CharacterProfileService;
use App\Application\Services\UserCharacterService;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowQuest;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;

function asyncResponse(array $data): FulfilledPromise
{
    return new FulfilledPromise(new Response(200, [], json_encode($data, JSON_THROW_ON_ERROR)));
}

/**
 * @param  array<string, array<string, mixed>>  $endpointResponses
 */
function mockAsyncEndpoints(\Mockery\MockInterface $mock, array $endpointResponses): void
{
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getAsync');
    $exp->andReturnUsing(function (string $endpoint) use ($endpointResponses): FulfilledPromise {
        foreach ($endpointResponses as $pattern => $data) {
            if (str_contains($endpoint, $pattern)) {
                return asyncResponse($data);
            }
        }

        return asyncResponse([]);
    });
}

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

    // Summary is fetched synchronously
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

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentMythicSeasonId');
    $seasonExp->andReturn(0);

    $userCharMock = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $classIconsExp */
    $classIconsExp = $userCharMock->shouldReceive('getClassIcons');
    $classIconsExp->andReturn([7 => 'https://render.com/class-icon.jpg']);

    // All other endpoints are fetched async
    mockAsyncEndpoints($mock, [
        'character-media' => [
            'assets' => [
                ['key' => 'avatar', 'value' => 'https://render.com/avatar.jpg'],
                ['key' => 'inset', 'value' => 'https://render.com/inset.jpg'],
            ],
        ],
        'quests/completed' => ['quests' => [['id' => 100]]],
        'achievements' => ['achievements' => []],
        'collections/mounts' => ['mounts' => [['mount' => ['id' => 200]]]],
        'collections/pets' => ['pets' => [['species' => ['id' => 300]]]],
        'collections/decor' => ['decor_collected' => [['decor' => ['id' => 500]]]],
        '/professions' => ['primaries' => [], 'secondaries' => []],
        '/reputations' => ['reputations' => []],
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
    $exp->andReturn([
        'name' => 'Test',
        'realm' => ['name' => 'Test'],
        'race' => ['name' => 'Human'],
        'character_class' => ['id' => 1, 'name' => 'Warrior'],
        'level' => 80,
        'equipped_item_level' => 600,
        'faction' => ['name' => 'Alliance'],
    ]);

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentMythicSeasonId');
    $seasonExp->andReturn(0);

    $this->mock(UserCharacterService::class)->shouldReceive('getClassIcons')->andReturn([]);

    mockAsyncEndpoints($mock, [
        'character-media' => ['assets' => [['key' => 'avatar', 'value' => '']]],
        'quests/completed' => ['quests' => [['id' => 1]]],
        'achievements' => ['achievements' => []],
        'collections/mounts' => ['mounts' => []],
        'collections/pets' => ['pets' => []],
        'collections/decor' => ['decor_collected' => []],
        '/professions' => ['primaries' => [], 'secondaries' => []],
        '/reputations' => ['reputations' => []],
    ]);

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
    $exp->andReturn([
        'name' => 'Thrall',
        'realm' => ['name' => 'Hyjal'],
        'race' => ['name' => 'Orc'],
        'character_class' => ['id' => 7, 'name' => 'Chaman'],
        'level' => 80,
        'equipped_item_level' => 600,
        'faction' => ['name' => 'Horde'],
    ]);

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentMythicSeasonId');
    $seasonExp->andReturn(0);

    $this->mock(UserCharacterService::class)->shouldReceive('getClassIcons')->andReturn([]);

    mockAsyncEndpoints($mock, [
        'character-media' => ['assets' => [['key' => 'avatar', 'value' => '']]],
        'quests/completed' => ['quests' => [['id' => 1], ['id' => 2]]],
        'achievements' => ['achievements' => []],
        'collections/mounts' => ['mounts' => []],
        'collections/pets' => ['pets' => []],
        'collections/decor' => ['decor_collected' => []],
        '/professions' => ['primaries' => [], 'secondaries' => []],
        '/reputations' => ['reputations' => []],
    ]);

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
    $exp->andReturn([
        'name' => 'Test',
        'realm' => ['name' => 'Test'],
        'race' => ['name' => 'Human'],
        'character_class' => ['id' => 1, 'name' => 'Warrior'],
        'level' => 80,
        'equipped_item_level' => 600,
        'faction' => ['name' => 'Alliance'],
    ]);

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentMythicSeasonId');
    $seasonExp->andReturn(0);

    $this->mock(UserCharacterService::class)->shouldReceive('getClassIcons')->andReturn([]);

    // Mock async - decor returns 404 (empty), others return normally
    /** @var \Mockery\Expectation $asyncExp */
    $asyncExp = $mock->shouldReceive('getAsync');
    $asyncExp->andReturnUsing(function (string $endpoint): FulfilledPromise {
        if (str_contains($endpoint, 'collections/decor')) {
            // Simulate 404 → async resolves with empty body (handled as empty in fetchAsync)
            return new FulfilledPromise(
                new \GuzzleHttp\Psr7\Response(404, [], json_encode([], JSON_THROW_ON_ERROR)),
            );
        }

        $responses = [
            'character-media' => ['assets' => [['key' => 'avatar', 'value' => '']]],
            'quests/completed' => ['quests' => []],
            'achievements' => ['achievements' => []],
            'collections/mounts' => ['mounts' => []],
            'collections/pets' => ['pets' => []],
            '/professions' => ['primaries' => [], 'secondaries' => []],
            '/reputations' => ['reputations' => []],
        ];

        foreach ($responses as $pattern => $data) {
            if (str_contains($endpoint, $pattern)) {
                return asyncResponse($data);
            }
        }

        return asyncResponse([]);
    });

    $characterProfileService = resolve(CharacterProfileService::class);
    $characterProfileDTO = $characterProfileService->getProfile('test', 'test');

    // Decor API failed but profile still works — no decor collected
    expect($characterProfileDTO->decorCount)->toBe(0)
        ->and($characterProfileDTO->decor)->toHaveCount(1)
        ->and($characterProfileDTO->decor[0]['is_completed'])->toBeFalse();
});
