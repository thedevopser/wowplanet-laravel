<?php

declare(strict_types=1);

use App\Application\Import\ImportPipeline;
use App\Application\Import\ImportProgressStore;
use App\Application\Import\ImportStage;
use App\Application\Import\ImportStepStatus;
use App\Application\Import\ImportWaitReason;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use App\Models\WowImportState;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->importerMock = $this->mock(BlizzardBatchImporter::class);
    $this->apiClientMock = $this->mock(BlizzardApiClient::class);
    $this->apiClientMock->shouldReceive('currentBuild')->andReturn('12.1.0_68914')->byDefault();
});

function pipeline(): ImportPipeline
{
    return resolve(ImportPipeline::class);
}

test('beginning an import publishes a pending step per stage', function (): void {
    $importRun = pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false);

    expect($importRun->steps)->toHaveCount(2)
        ->and((new ImportProgressStore)->find('job-1'))->toEqual($importRun);
});

test('a stage already imported for this build is skipped rather than redone', function (): void {
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);

    $importRun = pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false);

    expect($importRun->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Skipped)
        ->and($importRun->step(ImportStage::Decor)->status)->toBe(ImportStepStatus::Pending);
});

test('forcing an import redoes the stages the build gate would have skipped', function (): void {
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);

    $importRun = pipeline()->begin('job-1', [ImportStage::Pets], force: true);

    expect($importRun->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Pending);
});

test('an import with nothing left to do is complete as soon as it begins', function (): void {
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);

    expect(pipeline()->begin('job-1', [ImportStage::Pets], force: false)->status())
        ->toBe(ImportStepStatus::Completed);
});

test('advancing runs the first stage left and publishes what it did', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();
    $this->importerMock->shouldNotReceive('importDecor');

    $importRun = pipeline()->advance(
        pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false),
        full: false,
        limit: null,
    );

    expect($importRun->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed)
        ->and((new ImportProgressStore)->find('job-1')?->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed);
});

test('a completed stage is remembered for this build, so a restart does not redo it', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false), false, null);

    expect(WowImportState::query()->where('entity', 'pets')->where('build', '12.1.0_68914')->exists())->toBeTrue();
});

test('a failed stage is not remembered, so the next run tries it again', function (): void {
    $this->importerMock->shouldReceive('importPets')->once()->andThrow(new RuntimeException('pet index unavailable'));

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false), false, null);

    expect(WowImportState::query()->where('entity', 'pets')->exists())->toBeFalse();
});

test('a failed stage does not stop the ones after it', function (): void {
    $this->importerMock->shouldReceive('importPets')->once()->andThrow(new RuntimeException('pet index unavailable'));
    $this->importerMock->shouldReceive('importDecor')->once();

    $run = pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false);
    $run = pipeline()->advance($run, false, null);
    $run = pipeline()->advance($run, false, null);

    expect($run->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Failed)
        ->and($run->step(ImportStage::Decor)->status)->toBe(ImportStepStatus::Completed)
        ->and($run->status())->toBe(ImportStepStatus::Failed);
});

test('advancing a finished import runs nothing more', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();

    $importRun = pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false), false, null);
    $again = pipeline()->advance($importRun, false, null);

    expect($again)->toEqual($importRun);
});

test('each pass publishes the hourly budget consumed', function (): void {
    $this->importerMock->shouldReceive('importPets')->once()->andReturnUsing(function (): void {
        resolve(HourlyBudgetGuard::class)->consume(1_200);
    });

    $importRun = pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false), false, null);

    expect($importRun->budgetUsed)->toBe(1_200);
});

test('an import running under an unknown build is not skipped by the gate', function (): void {
    $this->apiClientMock->shouldReceive('currentBuild')->andReturn(null);
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);
    $this->importerMock->shouldReceive('importPets')->once();

    $importRun = pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false), false, null);

    expect($importRun->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed);
});

test('the stage being run is named in the tracking while it runs', function (): void {
    $seen = null;
    $this->importerMock->shouldReceive('importPets')->once()->andReturnUsing(function () use (&$seen): void {
        $seen = (new ImportProgressStore)->find('job-1')?->currentStage();
    });

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false), false, null);

    expect($seen)->toBe(ImportStage::Pets);
});

test('a stage does not start while the reserved hourly ceiling is spent', function (): void {
    resolve(HourlyBudgetGuard::class)->consume(30_000);

    $this->importerMock->shouldNotReceive('importPets');

    $importRun = pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false), false, null);

    expect($importRun->wait?->reason)->toBe(ImportWaitReason::HourlyBudget)
        ->and($importRun->wait->seconds)->toBeGreaterThan(0)
        ->and($importRun->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Pending);
});

test('the reference socle runs even with the Blizzard quota spent, spending none of it', function (): void {
    resolve(HourlyBudgetGuard::class)->consume(30_000);

    Artisan::shouldReceive('call')->once()->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    $importRun = pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Reference], force: false), false, null);

    expect($importRun->step(ImportStage::Reference)->status)->toBe(ImportStepStatus::Completed);
});
