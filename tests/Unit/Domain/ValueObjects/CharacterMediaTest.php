<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\CharacterMedia;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CharacterMediaTest extends TestCase
{
    #[Test]
    public function itCreatesWithValidUrls(): void
    {
        $characterMedia = new CharacterMedia(
            avatarUrl: 'https://render.worldofwarcraft.com/avatar.jpg',
            insetUrl: 'https://render.worldofwarcraft.com/inset.jpg',
            mainUrl: 'https://render.worldofwarcraft.com/main.jpg',
        );

        $this->assertSame('https://render.worldofwarcraft.com/avatar.jpg', $characterMedia->avatarUrl);
        $this->assertSame('https://render.worldofwarcraft.com/inset.jpg', $characterMedia->insetUrl);
        $this->assertSame('https://render.worldofwarcraft.com/main.jpg', $characterMedia->mainUrl);
    }

    #[Test]
    public function itIsReadonly(): void
    {
        $characterMedia = new CharacterMedia(
            avatarUrl: 'https://example.com/avatar.jpg',
            insetUrl: 'https://example.com/inset.jpg',
            mainUrl: 'https://example.com/main.jpg',
        );

        $reflectionClass = new \ReflectionClass($characterMedia);
        $this->assertTrue($reflectionClass->isReadOnly());
    }
}
