<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use App\Infrastructure\Blizzard\Importers\AppearanceImporter;
use App\Jobs\ImportAppearancesJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
    Cache::flush();
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
}

test('the job re-dispatches itself when the budget is exhausted (work remains)', function (): void {
    Bus::fake();

    // Budget au-delà du plafond réservé → importChunk rend la main sans avancer.
    resolve(HourlyBudgetGuard::class)->consume(HourlyBudgetGuard::HOURLY_LIMIT);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockJobSlotIndexes($client, ['HEAD' => [321]]);
    $client->shouldNotReceive('getAsync');

    (new ImportAppearancesJob('job-1', full: false, offset: 0))->handle(resolve(AppearanceImporter::class));

    Bus::assertDispatched(fn (\App\Jobs\ImportAppearancesJob $importAppearancesJob): bool => $importAppearancesJob->offset === 0 && $importAppearancesJob->full === false);
    expect(Cache::get('admin_import:job-1')['status'])->toBe('running');
});

test('the job marks the import completed when there is nothing left to process', function (): void {
    Bus::fake();

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockJobSlotIndexes($client, []); // aucune apparence → done immédiat

    (new ImportAppearancesJob('job-2', full: true, offset: 0))->handle(resolve(AppearanceImporter::class));

    Bus::assertNotDispatched(ImportAppearancesJob::class);
    expect(Cache::get('admin_import:job-2')['status'])->toBe('completed');
});
