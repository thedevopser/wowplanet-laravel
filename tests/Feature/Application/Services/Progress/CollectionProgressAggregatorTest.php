<?php

declare(strict_types=1);

use App\Application\Services\Progress\CollectionProgressAggregator;
use App\Models\WowAppearance;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;

test('aggregateMounts returns mount list with completion status and category', function (): void {
    WowMount::factory()->create(['id' => 1, 'name_fr' => 'Loup', 'source' => 'Vendeur', 'category' => 'Classic']);
    WowMount::factory()->create(['id' => 2, 'name_fr' => 'Cheval', 'source' => 'Quête', 'category' => 'The War Within']);

    $aggregator = new CollectionProgressAggregator;
    $result = $aggregator->aggregateMounts([1]);

    expect($result)->toHaveCount(2);

    $loup = collect($result)->firstWhere('id', 1);
    $cheval = collect($result)->firstWhere('id', 2);
    expect($loup['is_completed'])->toBeTrue();
    expect($loup['source'])->toBe('Vendeur');
    expect($loup['category'])->toBe('Classic');
    expect($cheval['is_completed'])->toBeFalse();
    expect($cheval['category'])->toBe('The War Within');
});

test('aggregatePets returns pet list with completion status', function (): void {
    WowPet::factory()->create(['id' => 10, 'name_fr' => 'Chat']);
    WowPet::factory()->create(['id' => 11, 'name_fr' => 'Chien']);

    $aggregator = new CollectionProgressAggregator;
    $result = $aggregator->aggregatePets([10, 11]);

    expect($result)->toHaveCount(2);
    expect(collect($result)->every('is_completed', true))->toBeTrue();
});

test('aggregateDecor returns decor list with completion status and category', function (): void {
    WowDecor::factory()->create(['id' => 100, 'name_fr' => 'Statue', 'item_id' => 999, 'category' => 'The War Within', 'source' => 'Quest']);
    WowDecor::factory()->create(['id' => 101, 'name_fr' => 'Torche', 'item_id' => 998, 'category' => 'Midnight', 'source' => 'Vendor']);

    $aggregator = new CollectionProgressAggregator;
    $result = $aggregator->aggregateDecor([100]);

    expect($result)->toHaveCount(2);

    $statue = collect($result)->firstWhere('id', 100);
    $torche = collect($result)->firstWhere('id', 101);
    expect($statue['is_completed'])->toBeTrue();
    expect($statue['item_id'])->toBe(999);
    expect($statue['category'])->toBe('The War Within');
    expect($statue['source'])->toBe('Quest');
    expect($torche['is_completed'])->toBeFalse();
    expect($torche['category'])->toBe('Midnight');
    expect($torche['source'])->toBe('Vendor');
});

test('aggregateAppearances returns per-slot completed/total counters', function (): void {
    WowAppearance::factory()->create(['id' => 1, 'slot' => 'HEAD', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 2, 'slot' => 'HEAD', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 3, 'slot' => 'HEAD', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 4, 'slot' => 'WEAPON', 'category' => 'Arme', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 5, 'slot' => 'WEAPON', 'category' => 'Arme', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 6, 'slot' => 'HEAD', 'category' => 'Armure', 'is_active' => false]);

    $aggregator = new CollectionProgressAggregator;
    // débloqué : 2 têtes actives + 1 tête inactive (ignorée) + 1 id inconnu (ignoré)
    $result = $aggregator->aggregateAppearances([1, 2, 6, 999]);

    $head = collect($result)->firstWhere('slot', 'HEAD');
    $weapon = collect($result)->firstWhere('slot', 'WEAPON');

    expect($head['total'])->toBe(3)
        ->and($head['completed'])->toBe(2)
        ->and($head['category'])->toBe('Armure')
        ->and($weapon['total'])->toBe(2)
        ->and($weapon['completed'])->toBe(0);
});

test('aggregateAppearances returns zero completed when nothing unlocked', function (): void {
    WowAppearance::factory()->create(['slot' => 'HEAD', 'is_active' => true]);

    $aggregator = new CollectionProgressAggregator;
    $result = $aggregator->aggregateAppearances([]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['completed'])->toBe(0)
        ->and($result[0]['total'])->toBe(1);
});

test('aggregateMounts returns empty array when no mounts exist', function (): void {
    $aggregator = new CollectionProgressAggregator;
    $result = $aggregator->aggregateMounts([1, 2, 3]);

    expect($result)->toBe([]);
});
