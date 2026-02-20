<?php

declare(strict_types=1);

use App\Domain\Entities\Character;
use App\Domain\ValueObjects\CharacterMedia;

test('it creates with all fields', function (): void {
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

    expect($character->name)->toBe('Thrall')
        ->and($character->realm)->toBe('Hyjal')
        ->and($character->race)->toBe('Orc')
        ->and($character->class)->toBe('Shaman')
        ->and($character->level)->toBe(80)
        ->and($character->ilvl)->toBe(620)
        ->and($character->faction)->toBe('Horde')
        ->and($character->media)->toBe($characterMedia);
});
