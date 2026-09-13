<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Blizzard\ImportBuildGate;
use App\Infrastructure\Reference\FactionReference;
use App\Infrastructure\Reference\ReferenceMaps;
use App\Jobs\ImportAppearancesJob;
use App\Models\WowImportState;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    Bus::fake();

    $this->importerMock = $this->mock(BlizzardBatchImporter::class);
    $this->referenceMapsMock = $this->mock(ReferenceMaps::class);
    $this->factionReferenceMock = $this->mock(FactionReference::class);
    $this->apiClientMock = $this->mock(BlizzardApiClient::class);

    $this->apiClientMock->shouldReceive('currentBuild')->andReturn('12.1.0_68914')->byDefault();

    $this->referenceMapsMock->shouldReceive('questExpansions')->andReturn([])->byDefault();
    $this->referenceMapsMock->shouldReceive('questFactions')->andReturn([])->byDefault();
    $this->referenceMapsMock->shouldReceive('zoneFactions')->andReturn([])->byDefault();
    $this->referenceMapsMock->shouldReceive('recipeFactions')->andReturn([])->byDefault();
    $this->factionReferenceMock->shouldReceive('factions')->andReturn([])->byDefault();
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

    Bus::assertDispatched(ImportAppearancesJob::class);
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

test('command dispatches a full appearance import job', function (): void {
    $this->artisan('app:wow-data-import', ['--type' => 'appearances', '--full' => true])->assertSuccessful();

    Bus::assertDispatched(fn (\App\Jobs\ImportAppearancesJob $importAppearancesJob): bool => $importAppearancesJob->full && $importAppearancesJob->offset === 0);
});

test('command dispatches an incremental appearance import job by default', function (): void {
    $this->artisan('app:wow-data-import', ['--type' => 'appearances'])->assertSuccessful();

    Bus::assertDispatched(fn (\App\Jobs\ImportAppearancesJob $importAppearancesJob): bool => $importAppearancesJob->full === false);
});

test('command displays stats table after import', function (): void {
    $this->importerMock->shouldReceive('importAchievements', 'importQuests', 'tagMirrorQuestFactions', 'importMounts', 'importPets', 'importProfessions', 'tagMirrorRecipeFactions', 'importDecor');

    $this->artisan('app:wow-data-import')
        ->assertSuccessful()
        ->expectsOutputToContain('Import Complete!');
});

// ─── détection de build ─────────────────────────────────────

test('it imports nothing when every requested entity already sits on the current build', function (): void {
    $gate = new ImportBuildGate;
    foreach (['achievements', 'quests', 'mounts', 'pets', 'professions', 'decor', 'appearances'] as $entity) {
        $gate->remember($entity, '12.1.0_68914');
    }

    $this->importerMock->shouldNotReceive('importAchievements');
    $this->importerMock->shouldNotReceive('importQuests');
    $this->importerMock->shouldNotReceive('importMounts');

    $this->artisan('app:wow-data-import')
        ->expectsOutputToContain('12.1.0_68914')
        ->assertSuccessful();

    Bus::assertNotDispatched(ImportAppearancesJob::class);
});

test('it reimports an unchanged build when --force is passed', function (): void {
    $gate = new ImportBuildGate;
    $gate->remember('quests', '12.1.0_68914');

    $this->importerMock->shouldReceive('importQuests')->once();
    $this->importerMock->shouldReceive('tagMirrorQuestFactions')->once();

    $this->artisan('app:wow-data-import', ['--type' => 'quests', '--force' => true])->assertSuccessful();
});

test('it imports the entities that lag behind and leaves the others alone', function (): void {
    (new ImportBuildGate)->remember('quests', '12.1.0_68914');

    $this->importerMock->shouldNotReceive('importQuests');
    $this->importerMock->shouldReceive('importMounts')->once();

    $this->artisan('app:wow-data-import', ['--type' => 'mounts'])->assertSuccessful();
    $this->artisan('app:wow-data-import', ['--type' => 'quests'])->assertSuccessful();
});

test('it records the build against each entity it imports', function (): void {
    $this->importerMock->shouldReceive('importMounts')->once();

    $this->artisan('app:wow-data-import', ['--type' => 'mounts'])->assertSuccessful();

    expect(WowImportState::query()->where('entity', 'mounts')->value('build'))->toBe('12.1.0_68914');
});

test('it imports as usual when the build cannot be determined', function (): void {
    $this->apiClientMock->shouldReceive('currentBuild')->andReturn(null);

    $this->importerMock->shouldReceive('importMounts')->once();

    $this->artisan('app:wow-data-import', ['--type' => 'mounts'])->assertSuccessful();

    expect(WowImportState::query()->where('entity', 'mounts')->exists())->toBeFalse();
});
