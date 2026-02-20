<?php

declare(strict_types=1);

use App\Application\DTOs\CharacterProfileDTO;
use App\Application\Services\CharacterProfileService;

test('show returns character profile', function (): void {
    $characterProfileDTO = new CharacterProfileDTO(
        name: 'Thrall',
        realm: 'Hyjal',
        race: 'Orc',
        class: 'Chaman',
        classId: 7,
        level: 80,
        ilvl: 620,
        faction: 'Horde',
        avatarUrl: 'https://example.com/avatar.jpg',
        classIconUrl: 'https://example.com/class-icon.jpg',
        collections: [],
        mountsCount: 150,
        petsCount: 80,
    );

    $mock = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getProfile');
    $exp->once()->with('hyjal', 'thrall')->andReturn($characterProfileDTO);

    $this->getJson('/api/character/hyjal/thrall')
        ->assertOk()
        ->assertJsonFragment([
            'name' => 'Thrall',
            'realm' => 'Hyjal',
            'level' => 80,
            'classId' => 7,
            'decorCount' => 0,
        ]);
});

test('show returns 404 when character not found', function (): void {
    $mock = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getProfile');
    $exp->once()->with('hyjal', 'unknown')->andThrow(new Exception('Character not found'));

    $this->getJson('/api/character/hyjal/unknown')
        ->assertNotFound()
        ->assertJsonStructure(['error', 'message']);
});
