<?php

declare(strict_types=1);

use App\Application\DTOs\AppearanceImportProgress;
use App\Application\Import\ImportStage;
use App\Application\Import\ImportStageRunner;
use App\Application\Import\ImportStep;
use App\Application\Import\ImportStepStatus;
use App\Application\Import\ImportWaitReason;
use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use App\Infrastructure\Reference\FactionReference;
use App\Infrastructure\Reference\ReferenceMaps;
use App\Models\WowMount;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->importerMock = $this->mock(BlizzardBatchImporter::class);
    $this->referenceMapsMock = $this->mock(ReferenceMaps::class);
    $this->factionReferenceMock = $this->mock(FactionReference::class);

    $this->referenceMapsMock->shouldReceive('questExpansions')->andReturn([])->byDefault();
    $this->referenceMapsMock->shouldReceive('questFactions')->andReturn([])->byDefault();
    $this->referenceMapsMock->shouldReceive('zoneFactions')->andReturn([])->byDefault();
    $this->referenceMapsMock->shouldReceive('recipeFactions')->andReturn([])->byDefault();
    $this->factionReferenceMock->shouldReceive('factions')->andReturn([])->byDefault();
});

function runStage(ImportStage $importStage, bool $full = false, ?int $limit = null): ImportStep
{
    return resolve(ImportStageRunner::class)->run(ImportStep::pending($importStage), $full, $limit)->step;
}

test('a stage runs its importer and comes back complete', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();

    expect(runStage(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed);
});

test('a stage reports the rows its importer wrote', function (): void {
    $this->importerMock->shouldReceive('importMounts')->once()->andReturnUsing(function (): void {
        WowMount::query()->upsert(
            [['id' => 1, 'name_fr' => 'Cheval', 'is_active' => true], ['id' => 2, 'name_fr' => 'Griffon', 'is_active' => true]],
            uniqueBy: ['id'],
            update: ['name_fr'],
        );
    });

    $importStep = runStage(ImportStage::Mounts);

    expect($importStep->rows->created)->toBe(2)
        ->and($importStep->rows->updated)->toBe(0)
        ->and($importStep->rows->deleted)->toBe(0);
});

test('a stage reports the API calls it consumed', function (): void {
    $this->importerMock->shouldReceive('importPets')->once()->andReturnUsing(function (): void {
        resolve(HourlyBudgetGuard::class)->consume(12);
    });

    expect(runStage(ImportStage::Pets)->apiCalls)->toBe(12);
});

test('the quest stage feeds the importer with the reference maps', function (): void {
    $this->referenceMapsMock->shouldReceive('questExpansions')->once()->andReturn([1 => 9]);
    $this->importerMock->shouldReceive('importQuests')
        ->once()
        ->withArgs(fn (array $areas, array $questExpansions): bool => $questExpansions === [1 => 9]);
    $this->importerMock->shouldReceive('tagMirrorQuestFactions')->once();

    expect(runStage(ImportStage::Quests)->status)->toBe(ImportStepStatus::Completed);
});

test('the profession stage tags the mirrored recipes', function (): void {
    $this->importerMock->shouldReceive('importProfessions')->once();
    $this->importerMock->shouldReceive('tagMirrorRecipeFactions')->once();

    expect(runStage(ImportStage::Professions)->status)->toBe(ImportStepStatus::Completed);
});

test('the reference stage runs the socle sync command', function (): void {
    Artisan::shouldReceive('call')->once()->with('app:wow-reference-sync', [])->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('Socle de référence — build 12.1.0.69587');

    expect(runStage(ImportStage::Reference)->status)->toBe(ImportStepStatus::Completed);
});

test('a socle sync that fails fails its stage with what the command said', function (): void {
    Artisan::shouldReceive('call')->once()->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('Téléchargement de Mount.db2 impossible.');

    $importStep = runStage(ImportStage::Reference);

    expect($importStep->status)->toBe(ImportStepStatus::Failed)
        ->and($importStep->error)->toContain('Mount.db2');
});

test('a stage that throws is reported failed instead of bringing the import down', function (): void {
    $this->importerMock->shouldReceive('importDecor')->once()->andThrow(new RuntimeException('decor index unavailable'));

    $importStep = runStage(ImportStage::Decor);

    expect($importStep->status)->toBe(ImportStepStatus::Failed)
        ->and($importStep->error)->toBe('decor index unavailable');
});

test('the wardrobe stage keeps its offset while windows remain', function (): void {
    $this->importerMock->shouldReceive('importAppearanceChunk')
        ->once()
        ->andReturn(new AppearanceImportProgress(done: false, offset: 143, total: 572, secondsUntilBudget: 0));

    $importStep = runStage(ImportStage::Appearances);

    expect($importStep->status)->toBe(ImportStepStatus::Running)
        ->and($importStep->offset)->toBe(143)
        ->and($importStep->total)->toBe(572);
});

test('the wardrobe stage completes when its last window is swept', function (): void {
    $this->importerMock->shouldReceive('importAppearanceChunk')
        ->once()
        ->andReturn(new AppearanceImportProgress(done: true, offset: 572, total: 572, secondsUntilBudget: 0));

    expect(runStage(ImportStage::Appearances)->status)->toBe(ImportStepStatus::Completed);
});

test('a wardrobe pass stopped by the hourly ceiling says so and for how long', function (): void {
    $this->importerMock->shouldReceive('importAppearanceChunk')
        ->once()
        ->andReturn(new AppearanceImportProgress(done: false, offset: 143, total: 572, secondsUntilBudget: 240));

    $importStageResult = resolve(ImportStageRunner::class)->run(ImportStep::pending(ImportStage::Appearances), false, null);

    expect($importStageResult->wait?->reason)->toBe(ImportWaitReason::HourlyBudget)
        ->and($importStageResult->wait->seconds)->toBe(240);
});

test('a stage that runs to its end waits on nothing', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();

    expect(resolve(ImportStageRunner::class)->run(ImportStep::pending(ImportStage::Pets), false, null)->wait)->toBeNull();
});

test('the wardrobe pass resumes from the offset of the step it is given', function (): void {
    $this->importerMock->shouldReceive('importAppearanceChunk')
        ->once()
        ->withArgs(fn (bool $full, int $offset): bool => $full && $offset === 143)
        ->andReturn(new AppearanceImportProgress(done: true, offset: 572, total: 572, secondsUntilBudget: 0));

    $importStep = ImportStep::pending(ImportStage::Appearances)
        ->advanced(App\Application\Import\RowTally::none(), 0, 0, offset: 143, total: 572);

    resolve(ImportStageRunner::class)->run($importStep, full: true, limit: null);
});
