<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\DTOs\CharacterProfileDTO;
use App\Application\Services\CharacterProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function showReturnsCharacterProfile(): void
    {
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

        $testResponse = $this->getJson('/api/character/hyjal/thrall');

        $testResponse->assertOk()
            ->assertJsonFragment([
                'name' => 'Thrall',
                'realm' => 'Hyjal',
                'level' => 80,
                'classId' => 7,
            ]);
    }

    #[Test]
    public function showReturns404WhenCharacterNotFound(): void
    {
        $mock = $this->mock(CharacterProfileService::class);
        /** @var \Mockery\Expectation $exp */
        $exp = $mock->shouldReceive('getProfile');
        $exp->once()->with('hyjal', 'unknown')->andThrow(new \Exception('Character not found'));

        $testResponse = $this->getJson('/api/character/hyjal/unknown');

        $testResponse->assertNotFound()
            ->assertJsonStructure(['error', 'message']);
    }
}
