<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Services;

use App\Application\Services\CharacterProfileService;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowQuest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function getProfileReturnsCorrectDTO(): void
    {
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
        $classMediaExp->withArgs(fn(string $endpoint): bool => str_contains($endpoint, 'media/playable-class'))
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

        $characterProfileService = resolve(CharacterProfileService::class);
        $characterProfileDTO = $characterProfileService->getProfile('Hyjal', 'Thrall');

        $this->assertSame('Thrall', $characterProfileDTO->name);
        $this->assertSame('Hyjal', $characterProfileDTO->realm);
        $this->assertSame(80, $characterProfileDTO->level);
        $this->assertSame(7, $characterProfileDTO->classId);
        $this->assertSame(1, $characterProfileDTO->mountsCount);
        $this->assertSame(1, $characterProfileDTO->petsCount);
        $this->assertSame('https://render.com/class-icon.jpg', $characterProfileDTO->classIconUrl);
    }

    #[Test]
    public function aggregateProgressGroupsByExpansionAndZone(): void
    {
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

            if (str_contains($endpoint, 'character-media')) {
                return ['assets' => [['key' => 'avatar', 'value' => '']]];
            }

            if (str_contains($endpoint, 'playable-class')) {
                return ['assets' => []];
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
        $this->assertSame(2, $classicData['quests']['total']);
        $this->assertSame(1, $classicData['quests']['completed']);

        /** @var array{quests: array{total: int, completed: int}, achievements: array{total: int, completed: int}} $twwData */
        $twwData = $characterProfileDTO->collections[10];
        $this->assertSame(1, $twwData['quests']['total']);
        $this->assertSame(0, $twwData['quests']['completed']);
    }

    #[Test]
    public function aggregateProgressFiltersQuestsByCharacterFaction(): void
    {
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
            if (str_contains($endpoint, 'character-media')) {
                return ['assets' => [['key' => 'avatar', 'value' => '']]];
            }
            if (str_contains($endpoint, 'playable-class')) {
                return ['assets' => []];
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

        $service = resolve(CharacterProfileService::class);
        $dto = $service->getProfile('hyjal', 'thrall');

        /** @var array{quests: array{total: int, completed: int}} $classicData */
        $classicData = $dto->collections[0];
        // Should see 2 quests (neutral + Horde), NOT the Alliance quest
        $this->assertSame(2, $classicData['quests']['total']);
        $this->assertSame(2, $classicData['quests']['completed']);
    }
}
