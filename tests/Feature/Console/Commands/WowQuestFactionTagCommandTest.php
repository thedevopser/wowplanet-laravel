<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Parsers\LuaAddonParser;

test('command builds reputation map and tags mirror quests', function (): void {
    $parserMock = $this->mock(LuaAddonParser::class);
    $parserMock->shouldReceive('getReputationFactionMap')
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
