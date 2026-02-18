<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Character;
use App\Domain\ValueObjects\CharacterMedia;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CharacterTest extends TestCase
{
    #[Test]
    public function itCreatesWithAllFields(): void
    {
        $characterMedia = new CharacterMedia(
            avatarUrl: 'https://example.com/avatar.jpg',
            insetUrl: 'https://example.com/inset.jpg',
            mainUrl: 'https://example.com/main.jpg',
        );

        $character = new Character(
            name: 'Thrall',
            realm: 'Hyjal',
            race: 'Orc',
            class: 'Shaman',
            level: 80,
            ilvl: 620,
            faction: 'Horde',
            media: $characterMedia,
        );

        $this->assertSame('Thrall', $character->name);
        $this->assertSame('Hyjal', $character->realm);
        $this->assertSame('Orc', $character->race);
        $this->assertSame('Shaman', $character->class);
        $this->assertSame(80, $character->level);
        $this->assertSame(620, $character->ilvl);
        $this->assertSame('Horde', $character->faction);
        $this->assertSame($characterMedia, $character->media);
    }
}
