<?php

declare(strict_types=1);

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomyLoader;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomySourceUnavailableException;
use App\Models\WowCollectionTaxonomy;

beforeEach(function (): void {
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

/**
 * @param  list<array{name: string, subcats: list<array{name: string, items: list<array<string, mixed>>}>}>  $categories
 */
function writeTaxonomySource(CollectionEntity $collectionEntity, array $categories): void
{
    file_put_contents(
        storage_path('app/blizzard/'.$collectionEntity->simpleArmoryFile()),
        json_encode($categories, JSON_THROW_ON_ERROR),
    );
}

/**
 * @param  list<array{int, string}>  $items  couples identifiant / nom
 * @return array{name: string, subcats: list<array{name: string, items: list<array<string, mixed>>}>}
 */
function taxonomyCategory(string $category, string $source, array $items): array
{
    return [
        'name' => $category,
        'subcats' => [[
            'name' => $source,
            'items' => array_map(
                static fn (array $item): array => ['ID' => $item[0], 'name' => $item[1], 'icon' => 'icon_name'],
                $items,
            ),
        ]],
    ];
}

function loadTaxonomy(CollectionEntity $collectionEntity): array
{
    return resolve(CollectionTaxonomyLoader::class)->load($collectionEntity);
}

test('it seeds an empty taxonomy from the curated file', function (): void {
    writeTaxonomySource(CollectionEntity::Mount, [
        taxonomyCategory('Classic', 'Reputation', [[6648, 'Coursier en sucre']]),
    ]);

    loadTaxonomy(CollectionEntity::Mount);

    $wowCollectionTaxonomy = WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Mount)->sole();

    expect($wowCollectionTaxonomy->entry_id)->toBe(6648)
        ->and($wowCollectionTaxonomy->category)->toBe('Classic')
        ->and($wowCollectionTaxonomy->source)->toBe('Reputation');
});

test('it reports what it read and what it actually inserted', function (): void {
    writeTaxonomySource(CollectionEntity::Pet, [
        taxonomyCategory('Classic', 'Drop', [[1, 'Un'], [2, 'Deux']]),
    ]);

    expect(loadTaxonomy(CollectionEntity::Pet))->toBe(['read' => 2, 'inserted' => 2, 'skipped' => 0]);
});

test('it never overwrites a row already in the taxonomy', function (): void {
    WowCollectionTaxonomy::factory()->create([
        'entity' => CollectionEntity::Mount,
        'entry_id' => 6648,
        'category' => 'Ajustement maison',
        'source' => 'Source arbitrée à la main',
    ]);

    writeTaxonomySource(CollectionEntity::Mount, [
        taxonomyCategory('Classic', 'Reputation', [[6648, 'Coursier en sucre']]),
    ]);

    loadTaxonomy(CollectionEntity::Mount);

    $wowCollectionTaxonomy = WowCollectionTaxonomy::query()->where('entry_id', 6648)->sole();

    expect($wowCollectionTaxonomy->category)->toBe('Ajustement maison')
        ->and($wowCollectionTaxonomy->source)->toBe('Source arbitrée à la main');
});

test('it counts a row it left alone as skipped rather than inserted', function (): void {
    WowCollectionTaxonomy::factory()->create(['entity' => CollectionEntity::Mount, 'entry_id' => 1]);

    writeTaxonomySource(CollectionEntity::Mount, [
        taxonomyCategory('Classic', 'Drop', [[1, 'Un'], [2, 'Deux']]),
    ]);

    expect(loadTaxonomy(CollectionEntity::Mount))->toBe(['read' => 2, 'inserted' => 1, 'skipped' => 1]);
});

test('it adds only the unknown entries when replayed', function (): void {
    writeTaxonomySource(CollectionEntity::Decor, [
        taxonomyCategory('The War Within', 'Quest', [[1, 'Un']]),
    ]);
    loadTaxonomy(CollectionEntity::Decor);

    writeTaxonomySource(CollectionEntity::Decor, [
        taxonomyCategory('The War Within', 'Quest', [[1, 'Un'], [2, 'Deux']]),
    ]);

    expect(loadTaxonomy(CollectionEntity::Decor))->toBe(['read' => 2, 'inserted' => 1, 'skipped' => 1])
        ->and(WowCollectionTaxonomy::query()->count())->toBe(2);
});

test('it is idempotent: a second identical run inserts nothing', function (): void {
    writeTaxonomySource(CollectionEntity::Mount, [
        taxonomyCategory('Legion', 'Class Hall', [[1, 'Un'], [2, 'Deux']]),
    ]);
    loadTaxonomy(CollectionEntity::Mount);

    expect(loadTaxonomy(CollectionEntity::Mount))->toBe(['read' => 2, 'inserted' => 0, 'skipped' => 2]);
});

test('it keeps the last ranking of an identifier listed under several categories, as the catalog does today', function (): void {
    writeTaxonomySource(CollectionEntity::Mount, [
        taxonomyCategory('Limited Time', 'Trading Post: September', [[2628, 'Rênes']]),
        taxonomyCategory('Past Limited Time', 'Trading Post Originals', [[2628, 'Rênes']]),
    ]);

    expect(loadTaxonomy(CollectionEntity::Mount))->toBe(['read' => 1, 'inserted' => 1, 'skipped' => 0]);

    $wowCollectionTaxonomy = WowCollectionTaxonomy::query()->sole();

    expect($wowCollectionTaxonomy->category)->toBe('Past Limited Time')
        ->and($wowCollectionTaxonomy->source)->toBe('Trading Post Originals');
});

test('it curates an entry whose category is empty as ranked nowhere, not as absent', function (): void {
    writeTaxonomySource(CollectionEntity::Pet, [
        taxonomyCategory('', '', [[1, 'Un']]),
    ]);

    loadTaxonomy(CollectionEntity::Pet);

    $wowCollectionTaxonomy = WowCollectionTaxonomy::query()->sole();

    expect($wowCollectionTaxonomy->category)->toBeNull()
        ->and($wowCollectionTaxonomy->source)->toBeNull();
});

test('it loads each collection into its own bucket', function (): void {
    writeTaxonomySource(CollectionEntity::Mount, [taxonomyCategory('Classic', 'Drop', [[1, 'Un']])]);
    writeTaxonomySource(CollectionEntity::Pet, [taxonomyCategory('Legion', 'Quest', [[1, 'Un']])]);

    loadTaxonomy(CollectionEntity::Mount);
    loadTaxonomy(CollectionEntity::Pet);

    expect(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Mount)->sole()->category)->toBe('Classic')
        ->and(WowCollectionTaxonomy::query()->where('entity', CollectionEntity::Pet)->sole()->category)->toBe('Legion');
});

test('it loads a volume larger than one insert chunk', function (): void {
    $items = array_map(static fn (int $id): array => [$id, 'Entrée '.$id], range(1, 1200));

    writeTaxonomySource(CollectionEntity::Decor, [taxonomyCategory('General', 'Vendor', $items)]);

    expect(loadTaxonomy(CollectionEntity::Decor))->toBe(['read' => 1200, 'inserted' => 1200, 'skipped' => 0])
        ->and(WowCollectionTaxonomy::query()->count())->toBe(1200);
});

test('it fails loudly when the curated file is missing, rather than emptying nothing in silence', function (): void {
    expect(fn (): array => loadTaxonomy(CollectionEntity::Mount))
        ->toThrow(TaxonomySourceUnavailableException::class, 'mounts.json');
});

test('it leaves the taxonomy untouched when the curated file is missing', function (): void {
    WowCollectionTaxonomy::factory()->create(['entity' => CollectionEntity::Mount, 'entry_id' => 1]);

    try {
        loadTaxonomy(CollectionEntity::Mount);
    } catch (TaxonomySourceUnavailableException) {
        // L'état de la table est ce qui est vérifié ici.
    }

    expect(WowCollectionTaxonomy::query()->count())->toBe(1);
});

test('it fails loudly when the curated file holds no usable entry', function (): void {
    writeTaxonomySource(CollectionEntity::Pet, []);

    expect(fn (): array => loadTaxonomy(CollectionEntity::Pet))
        ->toThrow(TaxonomySourceUnavailableException::class, 'pets.json');
});
