<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\CharacterProfileDTO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CharacterProfileDTOTest extends TestCase
{
    #[Test]
    public function it_creates_with_all_fields(): void
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
            collections: [
                0 => [
                    'quests' => ['total' => 100, 'completed' => 50, 'zones' => []],
                    'achievements' => ['total' => 200, 'completed' => 100, 'categories' => []],
                ],
            ],
            mountsCount: 150,
            petsCount: 80,
            mounts: [['id' => 1, 'name' => 'Loup noir', 'is_completed' => true]],
            pets: [['id' => 1, 'name' => 'Dragonnet', 'is_completed' => false]],
            decorCount: 50,
            decor: [['id' => 500, 'name' => 'Foyer orné', 'is_completed' => true, 'item_id' => 245000, 'icon_url' => 'https://example.com/icon.jpg']],
        );

        $this->assertSame('Thrall', $characterProfileDTO->name);
        $this->assertSame('Hyjal', $characterProfileDTO->realm);
        $this->assertSame('Orc', $characterProfileDTO->race);
        $this->assertSame('Chaman', $characterProfileDTO->class);
        $this->assertSame(7, $characterProfileDTO->classId);
        $this->assertSame(80, $characterProfileDTO->level);
        $this->assertSame(620, $characterProfileDTO->ilvl);
        $this->assertSame('Horde', $characterProfileDTO->faction);
        $this->assertSame(150, $characterProfileDTO->mountsCount);
        $this->assertSame(80, $characterProfileDTO->petsCount);
        $this->assertCount(1, $characterProfileDTO->mounts);
        $this->assertCount(1, $characterProfileDTO->pets);
        $this->assertSame(50, $characterProfileDTO->decorCount);
        $this->assertCount(1, $characterProfileDTO->decor);
    }

    #[Test]
    public function it_is_readonly(): void
    {
        $characterProfileDTO = new CharacterProfileDTO(
            name: 'Test',
            realm: 'Realm',
            race: 'Human',
            class: 'Warrior',
            classId: 1,
            level: 1,
            ilvl: 1,
            faction: 'Alliance',
            avatarUrl: '',
            classIconUrl: '',
            collections: [],
            mountsCount: 0,
            petsCount: 0,
        );

        $reflectionClass = new \ReflectionClass($characterProfileDTO);
        $this->assertTrue($reflectionClass->isReadOnly());
    }

    #[Test]
    public function it_defaults_mounts_and_pets_to_empty_arrays(): void
    {
        $characterProfileDTO = new CharacterProfileDTO(
            name: 'Test',
            realm: 'Realm',
            race: 'Human',
            class: 'Warrior',
            classId: 1,
            level: 1,
            ilvl: 1,
            faction: 'Alliance',
            avatarUrl: '',
            classIconUrl: '',
            collections: [],
            mountsCount: 0,
            petsCount: 0,
        );

        $this->assertSame([], $characterProfileDTO->mounts);
        $this->assertSame([], $characterProfileDTO->pets);
        $this->assertSame(0, $characterProfileDTO->decorCount);
        $this->assertSame([], $characterProfileDTO->decor);
    }
}
