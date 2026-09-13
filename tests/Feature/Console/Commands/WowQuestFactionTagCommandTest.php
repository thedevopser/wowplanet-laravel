<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Reference\FactionReference;

test('command builds reputation map and tags mirror quests', function (): void {
    $factionReferenceMock = $this->mock(FactionReference::class);
    $factionReferenceMock->shouldReceive('factions')
        ->once()
        ->andReturn([1000 => 'Alliance', 1001 => 'Horde']);

    $importerMock = $this->mock(BlizzardBatchImporter::class);
    $importerMock->shouldReceive('tagMirrorQuestFactions')
        ->once()
        ->with([1000 => 'Alliance', 1001 => 'Horde']);

    $this->artisan('app:wow-quest-faction-tag')
        ->assertSuccessful()
        ->expectsOutputToContain('2 reputation factions mapped');
});
