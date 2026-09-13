<?php

declare(strict_types=1);

use App\Application\Import\ImportPipeline;
use App\Application\Import\ImportProgressStore;
use App\Application\Import\ImportStage;
use App\Application\Import\ImportStepStatus;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use App\Jobs\RunImportJob;
use App\Models\WowImportState;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
    Cache::flush();
    Bus::fake();

    $this->apiClientMock = $this->mock(BlizzardApiClient::class);
    $this->apiClientMock->shouldReceive('currentBuild')->andReturn('12.1.0_68914')->byDefault();
});

/**
 * Mocke les 18 index de slots ; ceux listés dans $slots reçoivent leurs apparences.
 *
 * @param  array<string, list<int>>  $slots
 */
function mockJobSlotIndexes(\Mockery\MockInterface $mock, array $slots): void
{
    $allSlots = [
        'HEAD', 'SHOULDER', 'BODY', 'CHEST', 'WAIST', 'LEGS', 'FEET', 'WRIST', 'HAND',
        'CLOAK', 'TABARD', 'WEAPON', 'SHIELD', 'RANGED', 'TWOHWEAPON', 'WEAPONMAINHAND',
        'WEAPONOFFHAND', 'HOLDABLE',
    ];

    foreach ($allSlots as $allSlot) {
        $ids = $slots[$allSlot] ?? [];
        $mock->shouldReceive('get')
            ->with('data/wow/item-appearance/slot/'.$allSlot, \Mockery::any())
            ->andReturn(['appearances' => array_map(fn (int $id): array => ['id' => $id], $ids)]);
    }

    $mock->shouldReceive('get')
        ->withArgs(fn (string $endpoint): bool => str_contains($endpoint, 'orderby=id:desc'))
        ->andReturn(['results' => [['data' => ['id' => 2500]]]]);
}

/**
 * @param  array<string, mixed>  $parameters
 */
function handleImportJob(string $jobId, string $command = 'app:wow-data-import', array $parameters = []): void
{
    (new RunImportJob($jobId, $command, $parameters))->handle(
        resolve(ImportPipeline::class),
        resolve(ImportProgressStore::class),
    );
}

test('the job runs the stage it was asked for and completes the import', function (): void {
    $importer = $this->mock(BlizzardBatchImporter::class);
    $importer->shouldReceive('importPets')->once();

    handleImportJob('job-1', parameters: ['--type' => 'pets']);

    expect((new ImportProgressStore)->find('job-1')?->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed);
    Bus::assertNotDispatched(RunImportJob::class);
});

test('an unfinished import re-dispatches itself to carry on', function (): void {
    resolve(HourlyBudgetGuard::class)->consume(HourlyBudgetGuard::HOURLY_LIMIT);

    mockJobSlotIndexes($this->apiClientMock, ['HEAD' => [321]]);
    $this->apiClientMock->shouldNotReceive('getAsync');

    handleImportJob('job-1', parameters: ['--type' => 'appearances']);

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->jobId === 'job-1');
    expect(Cache::get('admin_import:job-1')['status'])->toBe('running');
});

test('a wardrobe pass stopped by the hourly ceiling says so in the tracking', function (): void {
    resolve(HourlyBudgetGuard::class)->consume(HourlyBudgetGuard::HOURLY_LIMIT);

    mockJobSlotIndexes($this->apiClientMock, ['HEAD' => [321]]);

    handleImportJob('job-1', parameters: ['--type' => 'appearances']);

    expect((new ImportProgressStore)->payload('job-1')['waiting']['reason'])->toBe('hourly_budget');
});

test('an import with nothing left to sweep is marked complete', function (): void {
    mockJobSlotIndexes($this->apiClientMock, []);

    handleImportJob('job-2', parameters: ['--type' => 'appearances']);

    Bus::assertNotDispatched(RunImportJob::class);
    expect(Cache::get('admin_import:job-2')['status'])->toBe('completed');
});

test('a job picked up again resumes the run instead of starting it over', function (): void {
    $importer = $this->mock(BlizzardBatchImporter::class);
    $importer->shouldReceive('importPets')->once();

    handleImportJob('job-1', parameters: ['--type' => 'pets']);
    handleImportJob('job-1', parameters: ['--type' => 'pets']);

    expect((new ImportProgressStore)->find('job-1')?->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed);
});

test('a worker restarted with the tracking lost skips what the build gate already holds', function (): void {
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);

    $importer = $this->mock(BlizzardBatchImporter::class);
    $importer->shouldNotReceive('importPets');

    handleImportJob('job-1', parameters: ['--type' => 'pets']);

    expect((new ImportProgressStore)->find('job-1')?->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Skipped);
});

test('forcing the import redoes a stage the build gate holds', function (): void {
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);

    $importer = $this->mock(BlizzardBatchImporter::class);
    $importer->shouldReceive('importPets')->once();

    handleImportJob('job-1', parameters: ['--type' => 'pets', '--force' => true]);

    expect((new ImportProgressStore)->find('job-1')?->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed);
});

test('a plain command still runs through Artisan and publishes its output', function (): void {
    handleImportJob('job-3', 'app:wow-quest-faction-tag');

    expect(Cache::get('admin_import:job-3')['status'])->toBe('completed');
});

test('a plain command that throws is published as a failure', function (): void {
    handleImportJob('job-4', 'app:does-not-exist');

    expect(Cache::get('admin_import:job-4')['status'])->toBe('failed');
});

test('the import job runs on the imports queue', function (): void {
    expect((new RunImportJob('job-1', 'app:wow-data-import'))->queue)->toBe('imports');
});
