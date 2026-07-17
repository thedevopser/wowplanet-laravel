<?php

declare(strict_types=1);

use App\Application\DTOs\CharacterProfileDTO;
use App\Application\Services\CharacterProfileService;
use App\Application\Services\UserCharacterService;
use Inertia\Testing\AssertableInertia as Assert;

function characterPageDTO(): CharacterProfileDTO
{
    return new CharacterProfileDTO(
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
}

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
        ->assertJsonStructure(['error']);
});

test('page redirects to lowercase url', function (): void {
    $this->get('/character/HYJAL/THRALL')
        ->assertRedirect('/character/hyjal/thrall')
        ->assertStatus(301);
});

test('page renders Inertia component with character and meta', function (): void {
    $auth = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $authExp */
    $authExp = $auth->shouldReceive('isAuthenticated');
    $authExp->andReturnFalse();

    $profiles = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $profiles->shouldReceive('getProfile');
    $profileExp->once()->with('hyjal', 'thrall')->andReturn(characterPageDTO());

    $this->get('/character/hyjal/thrall')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('CharacterPage')
            ->where('character.name', 'Thrall')
            ->where('realm', 'hyjal')
            ->where('name', 'thrall')
            ->where('meta.ogType', 'profile')
            ->has('meta.jsonLd')
        );
});

test('page returns 404 when profile cannot be fetched', function (): void {
    $auth = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $authExp */
    $authExp = $auth->shouldReceive('isAuthenticated');
    $authExp->andReturnFalse();

    $profiles = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $profiles->shouldReceive('getProfile');
    $profileExp->once()->andThrow(new Exception('Blizzard API error'));

    $this->get('/character/hyjal/unknown')
        ->assertStatus(404)
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('CharacterPage')
            ->where('character', null)
            ->where('meta.jsonLd', null)
        );
});
