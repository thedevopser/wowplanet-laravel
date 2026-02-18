<?php

declare(strict_types=1);

namespace Tests\Feature\EndToEnd;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Models\WowAchievement;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowQuest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterProfileFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fullCharacterProfileFlow(): void
    {
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

        $this->assertSame('Thrall', $data['name']);
        $this->assertSame('Hyjal', $data['realm']);
        $this->assertSame(80, $data['level']);
        $this->assertSame(7, $data['classId']);
        $this->assertSame(1, $data['mountsCount']);
        $this->assertSame(1, $data['petsCount']);

        /** @var array<int, array{quests: array{total: int, completed: int}, achievements: array{total: int, completed: int}}> $collections */
        $collections = $data['collections'];
        $classicCollections = $collections[0];
        $this->assertSame(2, $classicCollections['quests']['total']);
        $this->assertSame(1, $classicCollections['quests']['completed']);
        $this->assertSame(1, $classicCollections['achievements']['total']);
        $this->assertSame(1, $classicCollections['achievements']['completed']);
    }

    #[Test]
    public function characterNotFoundReturns404(): void
    {
        $mock = $this->mock(BlizzardApiClient::class);
        /** @var \Mockery\Expectation $exp */
        $exp = $mock->shouldReceive('get');
        $exp->andThrow(new \Exception('Character not found'));

        $testResponse = $this->getJson('/api/character/hyjal/unknown');

        $testResponse->assertNotFound()
            ->assertJsonStructure(['error', 'message']);
    }
}
