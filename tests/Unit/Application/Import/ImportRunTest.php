<?php

declare(strict_types=1);

use App\Application\Import\ImportRun;
use App\Application\Import\ImportStage;
use App\Application\Import\ImportStep;
use App\Application\Import\ImportStepStatus;
use App\Application\Import\ImportWait;
use App\Application\Import\RowTally;

function runOf(ImportStage ...$stages): ImportRun
{
    return ImportRun::start('job-1', array_values($stages), startedAt: 1_000);
}

test('a run starts with one pending step per requested stage', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts);

    expect($importRun->steps)->toHaveCount(2)
        ->and($importRun->status())->toBe(ImportStepStatus::Pending)
        ->and($importRun->currentStage())->toBeNull();
});

test('a step replaces the one of the same stage and nothing else', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Mounts)->finished(new RowTally(2, 0, 0), apiCalls: 5, durationMs: 100));

    expect($importRun->step(ImportStage::Mounts)->status)->toBe(ImportStepStatus::Completed)
        ->and($importRun->step(ImportStage::Quests)->status)->toBe(ImportStepStatus::Pending);
});

test('the running stage is the one the import is on', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->advanced(RowTally::none(), 1, 10, 1, 4));

    expect($importRun->currentStage())->toBe(ImportStage::Quests)
        ->and($importRun->status())->toBe(ImportStepStatus::Running);
});

test('a run is complete when every stage is, and failed when one failed', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 10))
        ->withStep(ImportStep::pending(ImportStage::Mounts)->skipped());

    expect($importRun->status())->toBe(ImportStepStatus::Completed);

    $broken = $importRun->withStep(ImportStep::pending(ImportStage::Mounts)->failed('mount index unavailable', 10));

    expect($broken->status())->toBe(ImportStepStatus::Failed);
});

test('a failed stage does not end the run while stages remain', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->failed('boom', 10));

    expect($importRun->status())->toBe(ImportStepStatus::Running);
});

test('the fraction done spreads evenly over the stages', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 10))
        ->withStep(ImportStep::pending(ImportStage::Mounts)->advanced(RowTally::none(), 1, 10, offset: 1, total: 2));

    expect($importRun->fraction())->toBe(0.75);
});

test('elapsed time counts from the start of the run', function (): void {
    expect(runOf(ImportStage::Quests)->elapsedSeconds(now: 1_252))->toBe(252);
});

test('the estimate extrapolates the time left from how long the done stages took', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts, ImportStage::Pets, ImportStage::Decor)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 20_000));

    expect($importRun->etaSeconds())->toBe(60);
});

test('a stage skipped by the build gate does not drag the estimate down', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts, ImportStage::Pets)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 20_000))
        ->withStep(ImportStep::pending(ImportStage::Mounts)->skipped());

    expect($importRun->etaSeconds())->toBe(20);
});

test('nothing done yet leaves the estimate unsaid rather than invented', function (): void {
    expect(runOf(ImportStage::Quests)->etaSeconds())->toBeNull();
});

test('a finished run has no time left', function (): void {
    $importRun = runOf(ImportStage::Quests)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 10));

    expect($importRun->etaSeconds())->toBe(0);
});

test('the run carries what it is waiting on, and drops it when it stops waiting', function (): void {
    $importRun = runOf(ImportStage::Quests)->waitingOn(ImportWait::hourlyBudget(240));

    expect($importRun->wait?->seconds)->toBe(240)
        ->and($importRun->waitingOn(null)->wait)->toBeNull();
});

test('the summary names the stage in progress and what the run is waiting on', function (): void {
    $summary = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->advanced(RowTally::none(), 1, 10, 1, 4))
        ->waitingOn(ImportWait::hourlyBudget(240))
        ->withBudgetUsed(12_340)
        ->summary(now: 1_060);

    expect($summary)->toContain('Quêtes')
        ->and($summary)->toContain('plafond horaire')
        ->and($summary)->toContain('12 340');
});

test('the summary reports each finished stage with its rows, calls and duration', function (): void {
    $summary = runOf(ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Mounts)->finished(new RowTally(43, 2, 1), apiCalls: 5, durationMs: 1_100))
        ->summary(now: 1_060);

    expect($summary)->toContain('Montures')
        ->and($summary)->toContain('43 créées')
        ->and($summary)->toContain('2 mises à jour')
        ->and($summary)->toContain('1 supprimée')
        ->and($summary)->toContain('5 appels');
});

test('the summary reports a failed stage with its reason', function (): void {
    $summary = runOf(ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Mounts)->failed('mount index unavailable', 10))
        ->summary(now: 1_060);

    expect($summary)->toContain('mount index unavailable');
});

test('the summary says nothing of rows for a stage that counts none', function (): void {
    $summary = runOf(ImportStage::Reference)
        ->withStep(ImportStep::pending(ImportStage::Reference)->finished(RowTally::none(), apiCalls: 0, durationMs: 7_900))
        ->summary(now: 1_060);

    expect($summary)->toContain('Socle de référence')
        ->and($summary)->not->toContain('0 créées');
});

test('a run survives a round trip through the tracking payload', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Appearances)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(new RowTally(1, 2, 3), 4, 5))
        ->waitingOn(ImportWait::batch(286))
        ->withBudgetUsed(12_340);

    expect(ImportRun::fromArray($importRun->toArray()))->toEqual($importRun);
});

test('asking a run for a stage it does not carry is refused', function (): void {
    runOf(ImportStage::Quests)->step(ImportStage::Mounts);
})->throws(InvalidArgumentException::class);

test('a run with no stage at all has nothing left to do', function (): void {
    expect(ImportRun::start('job-1', [], startedAt: 1_000)->fraction())->toBe(1.0);
});

test('durations are reported in seconds, minutes or hours as they grow', function (int $elapsed, string $expected): void {
    $importRun = runOf(ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Mounts)->finished(RowTally::none(), 1, 10));

    expect($importRun->summary(now: 1_000 + $elapsed))->toContain($expected);
})->with([
    [30, '30 s'],
    [95, '1 min 35 s'],
    [3_661, '1 h 01 min'],
]);

test('a run held back by the quota before its first stage is running, not pending', function (): void {
    expect(runOf(ImportStage::Quests)->waitingOn(ImportWait::hourlyBudget(240))->status())
        ->toBe(ImportStepStatus::Running);
});
