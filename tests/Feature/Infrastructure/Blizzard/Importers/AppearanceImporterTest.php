<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\AppearanceImporter;
use App\Models\WowAppearance;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
    Cache::flush();
});

/**
 * Mocke les 18 index de slots : ceux listés dans $slots reçoivent leurs apparences,
 * les autres renvoient une liste vide.
 *
 * @param  array<string, list<int>>  $slots  [slotType => list<appearanceId>]
 */
function mockSlotIndexes(\Mockery\MockInterface $mock, array $slots, array $failing = []): void
{
    $allSlots = [
        'HEAD', 'SHOULDER', 'BODY', 'CHEST', 'WAIST', 'LEGS', 'FEET', 'WRIST', 'HAND',
        'CLOAK', 'TABARD', 'WEAPON', 'SHIELD', 'RANGED', 'TWOHWEAPON', 'WEAPONMAINHAND',
        'WEAPONOFFHAND', 'HOLDABLE',
    ];

    foreach ($allSlots as $allSlot) {
        $expectation = $mock->shouldReceive('get')
            ->with('data/wow/item-appearance/slot/'.$allSlot, \Mockery::any());

        if (in_array($allSlot, $failing, true)) {
            $expectation->andThrow(new \Exception('API error: 404 Not Found'));

            continue;
        }

        $ids = $slots[$allSlot] ?? [];
        $expectation->andReturn([
            'appearances' => array_map(fn (int $id): array => ['id' => $id], $ids),
        ]);
    }
}

/**
 * Mocke le détail d'une apparence.
 *
 * @param  list<array{id: int, name: string}>  $items
 */
function mockAppearanceDetail(\Mockery\MockInterface $mock, int $id, string $slotType, string $itemClass, array $items, int $mediaId): void
{
    $mock->shouldReceive('getAsync')
        ->with('data/wow/item-appearance/'.$id, \Mockery::any())
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'id' => $id,
            'slot' => ['type' => $slotType, 'name' => 'x'],
            'item_class' => ['name' => $itemClass, 'id' => 4],
            'items' => array_map(fn (array $item): array => ['id' => $item['id'], 'name' => $item['name']], $items),
            'media' => ['id' => $mediaId],
        ]))));
}

/**
 * Mocke toutes les recherches bulk d'items : chaque appel reçoit le set complet
 * (l'importer filtre par ID, les entrées hors plage sont ignorées).
 *
 * @param  list<array{id: int, quality: string, name: string}>  $items
 */
function mockItemSearch(\Mockery\MockInterface $mock, array $items): void
{
    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $endpoint): bool => str_starts_with($endpoint, 'data/wow/search/item?'))
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'results' => array_map(fn (array $item): array => ['data' => [
                'id' => $item['id'],
                'quality' => ['type' => $item['quality']],
                'name' => ['fr_FR' => $item['name']],
            ]], $items),
        ]))));
}

/**
 * Mocke toutes les recherches bulk de media (icônes).
 *
 * @param  list<array{id: int, icon: string, fdid: int}>  $medias
 */
function mockMediaSearch(\Mockery\MockInterface $mock, array $medias): void
{
    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $endpoint): bool => str_starts_with($endpoint, 'data/wow/search/media?'))
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'results' => array_map(fn (array $media): array => ['data' => [
                'id' => $media['id'],
                'assets' => [['key' => 'icon', 'value' => $media['icon'], 'file_data_id' => $media['fdid']]],
            ]], $medias),
        ]))));
}

test('it imports collectible appearances from the API slot indexes', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, [
        'HEAD' => [321],
        'BODY' => [400],
        'WEAPONMAINHAND' => [500],
    ]);

    mockAppearanceDetail($client, 321, 'HEAD', 'Armure', [
        ['id' => 11735, 'name' => 'Couvre-œil du forcené'],
        ['id' => 19945, 'name' => 'Couvre-œil en écailles de lézard'],
    ], 11735);
    mockAppearanceDetail($client, 400, 'BODY', 'Armure', [
        ['id' => 600, 'name' => 'Chemise élégante'],
    ], 600);
    mockAppearanceDetail($client, 500, 'WEAPONMAINHAND', 'Arme', [
        ['id' => 700, 'name' => 'Lame fidèle'],
    ], 700);

    mockItemSearch($client, [
        ['id' => 11735, 'quality' => 'RARE', 'name' => 'Couvre-œil du forcené'],
        ['id' => 19945, 'quality' => 'EPIC', 'name' => 'Couvre-œil en écailles de lézard'],
        ['id' => 600, 'quality' => 'COMMON', 'name' => 'Chemise élégante'],
        ['id' => 700, 'quality' => 'LEGENDARY', 'name' => 'Lame fidèle'],
    ]);

    mockMediaSearch($client, [
        ['id' => 11735, 'icon' => 'https://render.worldofwarcraft.com/eu/icons/56/inv_chest_samurai.jpg', 'fdid' => 132759],
        ['id' => 600, 'icon' => 'https://render.worldofwarcraft.com/eu/icons/56/inv_shirt_01.jpg', 'fdid' => 100001],
        ['id' => 700, 'icon' => 'https://render.worldofwarcraft.com/eu/icons/56/inv_sword_01.jpg', 'fdid' => 100002],
    ]);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->count())->toBe(3);

    $head = WowAppearance::query()->find(321);
    // item représentatif = meilleure qualité parmi les items liés
    expect($head->name_fr)->toBe('Couvre-œil en écailles de lézard');
    expect($head->item_id)->toBe(19945);
    expect($head->quality)->toBe(4);
    expect($head->slot)->toBe('HEAD');
    expect($head->category)->toBe('Armure');
    expect($head->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/inv_chest_samurai.jpg');
    expect($head->icon_file_data_id)->toBe(132759);
    expect($head->is_active)->toBeTrue();

    // vocabulaire de slots du front préservé : BODY → SHIRT, WEAPONMAINHAND → WEAPON
    expect(WowAppearance::query()->find(400)->slot)->toBe('SHIRT');

    $weapon = WowAppearance::query()->find(500);
    expect($weapon->slot)->toBe('WEAPON');
    expect($weapon->category)->toBe('Arme');
    expect($weapon->quality)->toBe(5);
});

test('it deletes stale appearances no longer present in the API slot indexes', function (): void {
    WowAppearance::factory()->create(['id' => 999, 'is_active' => true]);
    // Reliquat désactivé par une passe précédente : doit être purgé lui aussi.
    WowAppearance::factory()->create(['id' => 998, 'is_active' => false]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockAppearanceDetail($client, 321, 'HEAD', 'Armure', [['id' => 10, 'name' => 'Heaume']], 10);
    mockItemSearch($client, [['id' => 10, 'quality' => 'RARE', 'name' => 'Heaume']]);
    mockMediaSearch($client, [['id' => 10, 'icon' => 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg', 'fdid' => 1]]);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->find(999))->toBeNull()
        ->and(WowAppearance::query()->find(998))->toBeNull()
        ->and(WowAppearance::query()->find(321)->is_active)->toBeTrue();
});

test('it aborts without deleting anything when a single slot index fails', function (): void {
    WowAppearance::factory()->create(['id' => 999, 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    // 17 slots répondent, CLOAK non : un index partiel effacerait tout un slot.
    mockSlotIndexes($client, ['HEAD' => [321]], failing: ['CLOAK']);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->find(999))->not->toBeNull()
        ->and(WowAppearance::query()->find(321))->toBeNull();
});

test('it skips already complete appearances unless a full refresh is requested', function (): void {
    WowAppearance::factory()->create([
        'id' => 321,
        'name_fr' => 'Déjà importée',
        'slot' => 'HEAD',
        'item_id' => 10,
        'icon_url' => 'https://render.worldofwarcraft.com/eu/icons/56/old.jpg',
        'is_active' => true,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321, 322]]);

    // Seule la nouvelle apparence 322 doit être détaillée
    $client->shouldNotReceive('getAsync')->with('data/wow/item-appearance/321', \Mockery::any());
    mockAppearanceDetail($client, 322, 'HEAD', 'Armure', [['id' => 20, 'name' => 'Nouveau heaume']], 20);
    mockItemSearch($client, [['id' => 20, 'quality' => 'EPIC', 'name' => 'Nouveau heaume']]);
    mockMediaSearch($client, [['id' => 20, 'icon' => 'https://render.worldofwarcraft.com/eu/icons/56/b.jpg', 'fdid' => 2]]);

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->count())->toBe(2)
        ->and(WowAppearance::query()->find(321)->name_fr)->toBe('Déjà importée')
        ->and(WowAppearance::query()->find(322)->name_fr)->toBe('Nouveau heaume');
});

test('it refetches every appearance when a full refresh is requested', function (): void {
    WowAppearance::factory()->create([
        'id' => 321,
        'name_fr' => 'Ancien nom',
        'slot' => 'HEAD',
        'item_id' => 10,
        'icon_url' => 'https://render.worldofwarcraft.com/eu/icons/56/old.jpg',
        'is_active' => true,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    mockAppearanceDetail($client, 321, 'HEAD', 'Armure', [['id' => 10, 'name' => 'Nom actualisé']], 10);
    mockItemSearch($client, [['id' => 10, 'quality' => 'RARE', 'name' => 'Nom actualisé']]);
    mockMediaSearch($client, [['id' => 10, 'icon' => 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg', 'fdid' => 1]]);

    resolve(AppearanceImporter::class)->import(full: true);

    expect(WowAppearance::query()->find(321)->name_fr)->toBe('Nom actualisé');
});

test('it falls back to the representative item icon when the appearance media is unresolvable', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [900]]);
    // media.id 99999 n'existe pas dans l'API media : l'icône doit venir de l'item 10
    mockAppearanceDetail($client, 900, 'HEAD', 'Armure', [['id' => 10, 'name' => 'Heaume']], 99999);
    mockItemSearch($client, [['id' => 10, 'quality' => 'RARE', 'name' => 'Heaume']]);
    mockMediaSearch($client, [
        ['id' => 10, 'icon' => 'https://render.worldofwarcraft.com/eu/icons/56/item10.jpg', 'fdid' => 42],
    ]);

    resolve(AppearanceImporter::class)->import();

    $appearance = WowAppearance::query()->find(900);
    expect($appearance->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/item10.jpg')
        ->and($appearance->icon_file_data_id)->toBe(42);
});

test('it limits the number of fetched details for smoke-testing', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321, 322, 323]]);

    // Seule la première apparence doit être détaillée
    mockAppearanceDetail($client, 321, 'HEAD', 'Armure', [['id' => 10, 'name' => 'Heaume']], 10);
    $client->shouldNotReceive('getAsync')->with('data/wow/item-appearance/322', \Mockery::any());
    $client->shouldNotReceive('getAsync')->with('data/wow/item-appearance/323', \Mockery::any());
    mockItemSearch($client, [['id' => 10, 'quality' => 'RARE', 'name' => 'Heaume']]);
    mockMediaSearch($client, [['id' => 10, 'icon' => 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg', 'fdid' => 1]]);

    resolve(AppearanceImporter::class)->import(limit: 1);

    expect(WowAppearance::query()->count())->toBe(1)
        ->and(WowAppearance::query()->find(321))->not->toBeNull();
});

test('importChunk stops without sleeping when the hourly budget is exhausted', function (): void {
    // Budget déjà au-delà du plafond réservé aux imports → importChunk doit rendre la
    // main (le job se re-dispatchera), sans dormir ni récupérer aucun détail.
    resolve(\App\Infrastructure\Blizzard\HourlyBudgetGuard::class)->consume(\App\Infrastructure\Blizzard\HourlyBudgetGuard::HOURLY_LIMIT);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [321]]);
    $client->shouldNotReceive('getAsync');

    $appearanceImportProgress = resolve(AppearanceImporter::class)->importChunk(full: false, offset: 0, timeBoxSeconds: 600);

    expect($appearanceImportProgress->done)->toBeFalse();
    expect($appearanceImportProgress->offset)->toBe(0); // aucun avancement : la tranche sera rejouée
    expect($appearanceImportProgress->secondsUntilBudget)->toBeGreaterThan(0);
    expect(WowAppearance::query()->count())->toBe(0);
    Sleep::assertNeverSlept();
});

test('importChunk saves a slice and reports completion with the final offset', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [500]]);
    mockAppearanceDetail($client, 500, 'HEAD', 'Armure', [['id' => 10, 'name' => 'Heaume']], 10);
    mockItemSearch($client, [['id' => 10, 'quality' => 'RARE', 'name' => 'Heaume']]);
    mockMediaSearch($client, [['id' => 10, 'icon' => 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg', 'fdid' => 1]]);

    $appearanceImportProgress = resolve(AppearanceImporter::class)->importChunk(full: false, offset: 0, timeBoxSeconds: 600);

    expect($appearanceImportProgress->done)->toBeTrue();
    expect($appearanceImportProgress->offset)->toBe(1);
    expect($appearanceImportProgress->total)->toBe(1);
    expect(WowAppearance::query()->find(500)?->item_id)->toBe(10);
});

test('it returns early when every slot index fails', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    $client->shouldReceive('get')
        ->withArgs(fn (string $endpoint): bool => str_starts_with($endpoint, 'data/wow/item-appearance/slot/'))
        ->andThrow(new \Exception('API error: 500 Internal Server Error'));

    resolve(AppearanceImporter::class)->import();

    expect(WowAppearance::query()->count())->toBe(0);
});

test('it skips an appearance whose detail has no usable item', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockSlotIndexes($client, ['HEAD' => [888]]);
    mockAppearanceDetail($client, 888, 'HEAD', 'Armure', [], 0);
    mockItemSearch($client, []);
    mockMediaSearch($client, []);

    resolve(AppearanceImporter::class)->import();

    // Sans nom ni icône, la ligne serait affichée vide et compterait au dénominateur.
    // Elle entrera d'elle-même dès que l'API exposera un item lié.
    expect(WowAppearance::query()->find(888))->toBeNull();
});
