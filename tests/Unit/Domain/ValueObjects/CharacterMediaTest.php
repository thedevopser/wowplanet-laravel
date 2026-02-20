<?php

declare(strict_types=1);

use App\Domain\ValueObjects\CharacterMedia;

test('it creates with valid urls', function (): void {
    $characterMedia = new CharacterMedia(
        avatarUrl: 'https://render.worldofwarcraft.com/avatar.jpg',
        insetUrl: 'https://render.worldofwarcraft.com/inset.jpg',
        mainUrl: 'https://render.worldofwarcraft.com/main.jpg',
    );

    expect($characterMedia->avatarUrl)->toBe('https://render.worldofwarcraft.com/avatar.jpg')
        ->and($characterMedia->insetUrl)->toBe('https://render.worldofwarcraft.com/inset.jpg')
        ->and($characterMedia->mainUrl)->toBe('https://render.worldofwarcraft.com/main.jpg');
});

test('it is readonly', function (): void {
    $characterMedia = new CharacterMedia(
        avatarUrl: 'https://example.com/avatar.jpg',
        insetUrl: 'https://example.com/inset.jpg',
        mainUrl: 'https://example.com/main.jpg',
    );

    $reflectionClass = new ReflectionClass($characterMedia);
    expect($reflectionClass->isReadOnly())->toBeTrue();
});
