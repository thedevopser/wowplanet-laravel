<?php

declare(strict_types=1);

use App\Application\Import\ImportProgressStore;
use App\Application\Import\ImportRun;
use App\Application\Import\ImportStage;
use App\Application\Import\ImportStep;
use App\Application\Import\ImportWait;
use App\Application\Import\RowTally;
use App\Jobs\RunImportJob;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

test('admin page renders AdminPage via inertia for an administrator', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('AdminPage'));
});

test('admin page redirects a non-admin visitor to home', function (): void {
    $this->get('/admin')->assertRedirect('/');
});

test('admin page redirects an authenticated non-admin to home', function (): void {
    $this->withSession(['blizzard_user_token' => 'fake-token'])
        ->get('/admin')
        ->assertRedirect('/');
});

// ─── suivi d'un import ──────────────────────────────────────

test('the progress endpoint answers an unknown job as not found', function (): void {
    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/nobody')
        ->assertOk()
        ->assertJson(['status' => 'not_found']);
});

test('the progress endpoint details the stage, the budget and the timings', function (): void {
    (new ImportProgressStore)->save(
        ImportRun::start('job-1', [ImportStage::Quests, ImportStage::Mounts], now()->getTimestamp())
            ->withStep(ImportStep::pending(ImportStage::Quests)->advanced(RowTally::none(), 12, 900, offset: 1, total: 4))
            ->withBudgetUsed(12_340)
    );

    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/job-1')
        ->assertOk()
        ->assertJsonPath('status', 'running')
        ->assertJsonPath('stage', 'quests')
        ->assertJsonPath('stage_label', 'Quêtes')
        ->assertJsonPath('budget.used', 12_340)
        ->assertJsonPath('steps.0.api_calls', 12);
});

test('the progress endpoint says why an import waits', function (): void {
    (new ImportProgressStore)->save(
        ImportRun::start('job-1', [ImportStage::Mounts], now()->getTimestamp())
            ->waitingOn(ImportWait::hourlyBudget(240))
    );

    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/job-1')
        ->assertOk()
        ->assertJsonPath('waiting.reason', 'hourly_budget')
        ->assertJsonPath('waiting.seconds', 240);
});

test('the progress endpoint is closed to anyone but an administrator', function (): void {
    $this->getJson('/api/admin/import/job-1')->assertForbidden();
});

test('starting an import queues the orchestrated job and hands back its id', function (): void {
    Bus::fake();

    $response = $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['command' => 'app:wow-data-import', 'type' => 'all']);

    $response->assertOk()->assertJsonStructure(['jobId']);

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->command === 'app:wow-data-import'
        && $runImportJob->parameters['--type'] === 'all');
});

test('the reference socle can be imported on its own from the panel', function (): void {
    Bus::fake();

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['command' => 'app:wow-data-import', 'type' => 'reference'])
        ->assertOk();

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->parameters['--type'] === 'reference');
});

test('an import that starts is already trackable', function (): void {
    Bus::fake();

    $jobId = $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['command' => 'app:wow-data-import'])
        ->json('jobId');

    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/'.$jobId)
        ->assertOk()
        ->assertJsonPath('status', 'pending');
});
