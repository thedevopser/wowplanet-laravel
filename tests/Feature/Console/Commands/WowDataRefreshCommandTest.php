<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Parsers\LuaAddonParser;

beforeEach(function (): void {
    $this->importerMock = $this->mock(BlizzardBatchImporter::class);
    $this->parserMock = $this->mock(LuaAddonParser::class);

    $this->parserMock->shouldReceive('getAchievementExpansionMap')->andReturn([])->byDefault();
    $this->parserMock->shouldReceive('buildAreaExpansionMap')->andReturn([])->byDefault();
    $this->parserMock->shouldReceive('getQuestExpansionMap')->andReturn([])->byDefault();
    $this->parserMock->shouldReceive('getQuestFactionMap')->andReturn([])->byDefault();
    $this->parserMock->shouldReceive('getZoneFactionMap')->andReturn([])->byDefault();
    $this->parserMock->shouldReceive('getReputationFactionMap')->andReturn([])->byDefault();
});

test('command aborts when user declines confirmation', function (): void {
    $this->importerMock->shouldNotReceive('importAchievements');
    $this->importerMock->shouldNotReceive('importQuests');

    $this->artisan('app:wow-data-refresh')
        ->expectsConfirmation('This will DELETE all existing data and re-import from scratch. Continue?', 'no')
        ->expectsOutputToContain('Aborted.');
});

test('command truncates and reimports all with --force', function (): void {
    $this->importerMock->shouldReceive('importAchievements')->once();
    $this->importerMock->shouldReceive('importQuests')->once();
    $this->importerMock->shouldReceive('tagMirrorQuestFactions')->once();
    $this->importerMock->shouldReceive('importMounts')->once();
    $this->importerMock->shouldReceive('importPets')->once();

    $this->artisan('app:wow-data-refresh', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Refresh Complete!');
});

test('command refreshes only quests with --type=quests --force', function (): void {
    $this->importerMock->shouldReceive('importQuests')->once();
    $this->importerMock->shouldReceive('tagMirrorQuestFactions')->once();
    $this->importerMock->shouldNotReceive('importAchievements');
    $this->importerMock->shouldNotReceive('importMounts');

    $this->artisan('app:wow-data-refresh', ['--type' => 'quests', '--force' => true])
        ->assertSuccessful();
});

test('command refreshes only achievements with --type=achievements --force', function (): void {
    $this->importerMock->shouldReceive('importAchievements')->once();
    $this->importerMock->shouldNotReceive('importQuests');

    $this->artisan('app:wow-data-refresh', ['--type' => 'achievements', '--force' => true])
        ->assertSuccessful();
});
