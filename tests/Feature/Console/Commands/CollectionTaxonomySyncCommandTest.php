<?php

declare(strict_types=1);

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

/**
 * @param  array<string, int>  $entryCounts  entité => nombre d'entrées curées à écrire
 */
function writeCuratedCollections(array $entryCounts): void
{
    foreach ($entryCounts as $entity => $count) {
        $collectionEntity = CollectionEntity::fromOption($entity);

        file_put_contents(
            storage_path('app/blizzard/'.$collectionEntity->simpleArmoryFile()),
            json_encode([[
                'name' => 'Classic',
                'subcats' => [[
                    'name' => 'Vendor',
                    'items' => array_map(
                        static fn (int $id): array => ['ID' => $id, 'name' => 'Entrée '.$id, 'icon' => 'icon'],
                        range(1, $count),
                    ),
                ]],
            ]], JSON_THROW_ON_ERROR),
        );
    }
}

function syncTaxonomyOutput(string ...$arguments): string
{
    Artisan::call('app:collection-taxonomy-sync', $arguments === [] ? [] : ['--entity' => $arguments[0]]);

    return Artisan::output();
}

test('it seeds the three collections in one run', function (): void {
    writeCuratedCollections(['mount' => 3, 'pet' => 2, 'decor' => 4]);

    $this->artisan('app:collection-taxonomy-sync')->assertSuccessful();

    expect(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Mount)->count())->toBe(3)
        ->and(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Pet)->count())->toBe(2)
        ->and(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Decor)->count())->toBe(4);
});

test('it reports how many entries each collection gained', function (): void {
    writeCuratedCollections(['mount' => 3, 'pet' => 2, 'decor' => 4]);

    $output = syncTaxonomyOutput();

    expect($output)->toContain('mount')
        ->and($output)->toContain('(+3)')
        ->and($output)->toContain('(+2)')
        ->and($output)->toContain('(+4)');
});

test('it reports an unchanged collection as such on a replay', function (): void {
    writeCuratedCollections(['mount' => 3, 'pet' => 2, 'decor' => 4]);
    syncTaxonomyOutput();

    expect(syncTaxonomyOutput())->toContain('(=)');
});

test('it adds nothing on a replay without a new curated entry', function (): void {
    writeCuratedCollections(['mount' => 3, 'pet' => 2, 'decor' => 4]);
    $this->artisan('app:collection-taxonomy-sync')->assertSuccessful();

    $this->artisan('app:collection-taxonomy-sync')->assertSuccessful();

    expect(WowCollectionTaxonomy::query()->count())->toBe(9);
});

test('it adds only the new entries of a later patch', function (): void {
    writeCuratedCollections(['mount' => 3, 'pet' => 2, 'decor' => 4]);
    $this->artisan('app:collection-taxonomy-sync')->assertSuccessful();

    writeCuratedCollections(['mount' => 5, 'pet' => 2, 'decor' => 4]);
    $this->artisan('app:collection-taxonomy-sync')->assertSuccessful();

    expect(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Mount)->count())->toBe(5);
});

test('it leaves a manual arbitration in place', function (): void {
    writeCuratedCollections(['mount' => 3, 'pet' => 2, 'decor' => 4]);
    $this->artisan('app:collection-taxonomy-sync')->assertSuccessful();

    WowCollectionTaxonomy::query()
        ->where('entity', CollectionEntity::Mount)
        ->where('entry_id', 1)
        ->update(['category' => 'Arbitré à la main', 'source' => 'Source arbitrée']);

    $this->artisan('app:collection-taxonomy-sync')->assertSuccessful();

    $wowCollectionTaxonomy = WowCollectionTaxonomy::query()
        ->where('entity', CollectionEntity::Mount)
        ->where('entry_id', 1)
        ->sole();

    expect($wowCollectionTaxonomy->category)->toBe('Arbitré à la main')
        ->and($wowCollectionTaxonomy->source)->toBe('Source arbitrée');
});

test('it synchronises a single collection when asked', function (): void {
    writeCuratedCollections(['mount' => 3, 'pet' => 2, 'decor' => 4]);

    $this->artisan('app:collection-taxonomy-sync', ['--entity' => 'pet'])->assertSuccessful();

    expect(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Pet)->count())->toBe(2)
        ->and(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Mount)->count())->toBe(0);
});

test('it fails on a collection it does not know, without touching the taxonomy', function (): void {
    writeCuratedCollections(['mount' => 3]);
    $this->artisan('app:collection-taxonomy-sync', ['--entity' => 'mount'])->assertSuccessful();

    $this->artisan('app:collection-taxonomy-sync', ['--entity' => 'toy'])->assertFailed();

    expect(WowCollectionTaxonomy::query()->count())->toBe(3);
});

test('it fails when a curated file is missing', function (): void {
    writeCuratedCollections(['mount' => 3, 'pet' => 2]);

    $this->artisan('app:collection-taxonomy-sync')->assertFailed();
});

test('it loads nothing at all when one curated file is missing', function (): void {
    writeCuratedCollections(['mount' => 3, 'pet' => 2]);

    $this->artisan('app:collection-taxonomy-sync')->assertFailed();

    expect(WowCollectionTaxonomy::query()->count())->toBe(0);
});
