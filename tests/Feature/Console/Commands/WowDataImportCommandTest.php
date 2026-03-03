<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Parsers\LuaAddonParser;

beforeEach(function (): void {
    $this->importerMock = $this->mock(BlizzardBatchImporter::class);
    $this->parserMock = $this->mock(LuaAddonParser::class);

    // Default: parser returns empty arrays
    $this->parserMock->shouldReceive('buildAreaExpansionMap')->andReturn([])->byDefault();
    $this->parserMock->shouldReceive('getQuestExpansionMap')->andReturn([])->byDefault();
    $this->parserMock->shouldReceive('getQuestFactionMap')->andReturn([])->byDefault();
    $this->parserMock->shouldReceive('getZoneFactionMap')->andReturn([])->byDefault();
    $this->parserMock->shouldReceive('getReputationFactionMap')->andReturn([])->byDefault();
    $this->parserMock->shouldReceive('getRecipeFactionMap')->andReturn([])->byDefault();
    $this->parserMock->shouldReceive('getSpellNameMap')->andReturn([])->byDefault();
});

test('command imports all types by default', function (): void {
    $this->importerMock->shouldReceive('importAchievements')->once();
    $this->importerMock->shouldReceive('importQuests')->once();
    $this->importerMock->shouldReceive('tagMirrorQuestFactions')->once();
    $this->importerMock->shouldReceive('importMounts')->once();
    $this->importerMock->shouldReceive('importPets')->once();
    $this->importerMock->shouldReceive('importProfessions')->once();
    $this->importerMock->shouldReceive('tagMirrorRecipeFactions')->once();
    $this->importerMock->shouldReceive('importDecor')->once();

    $this->artisan('app:wow-data-import')->assertSuccessful();
});

test('command imports only quests when --type=quests', function (): void {
    $this->importerMock->shouldReceive('importQuests')->once();
    $this->importerMock->shouldReceive('tagMirrorQuestFactions')->once();
    $this->importerMock->shouldNotReceive('importAchievements');
    $this->importerMock->shouldNotReceive('importMounts');

    $this->artisan('app:wow-data-import', ['--type' => 'quests'])->assertSuccessful();
});

test('command imports only achievements when --type=achievements', function (): void {
    $this->importerMock->shouldReceive('importAchievements')->once();
    $this->importerMock->shouldNotReceive('importQuests');

    $this->artisan('app:wow-data-import', ['--type' => 'achievements'])->assertSuccessful();
});

test('command imports only mounts when --type=mounts', function (): void {
    $this->importerMock->shouldReceive('importMounts')->once();
    $this->importerMock->shouldNotReceive('importQuests');
    $this->importerMock->shouldNotReceive('importAchievements');

    $this->artisan('app:wow-data-import', ['--type' => 'mounts'])->assertSuccessful();
});

test('command imports only professions when --type=professions', function (): void {
    $this->importerMock->shouldReceive('importProfessions')->once();
    $this->importerMock->shouldReceive('tagMirrorRecipeFactions')->once();
    $this->importerMock->shouldNotReceive('importQuests');

    $this->artisan('app:wow-data-import', ['--type' => 'professions'])->assertSuccessful();
});

test('command displays stats table after import', function (): void {
    $this->importerMock->shouldReceive('importAchievements', 'importQuests', 'tagMirrorQuestFactions', 'importMounts', 'importPets', 'importProfessions', 'tagMirrorRecipeFactions', 'importDecor');

    $this->artisan('app:wow-data-import')
        ->assertSuccessful()
        ->expectsOutputToContain('Import Complete!');
});
