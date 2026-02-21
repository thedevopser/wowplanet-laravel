<?php

declare(strict_types=1);

use App\Application\Services\Progress\CollectionProgressAggregator;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;

test('aggregateMounts returns mount list with completion status', function (): void {
    WowMount::factory()->create(['id' => 1, 'name_fr' => 'Loup', 'source' => 'Vendeur']);
    WowMount::factory()->create(['id' => 2, 'name_fr' => 'Cheval', 'source' => 'Quête']);

    $aggregator = new CollectionProgressAggregator;
    $result = $aggregator->aggregateMounts([1]);

    expect($result)->toHaveCount(2);

    $loup = collect($result)->firstWhere('id', 1);
    $cheval = collect($result)->firstWhere('id', 2);
    expect($loup['is_completed'])->toBeTrue();
    expect($loup['source'])->toBe('Vendeur');
    expect($cheval['is_completed'])->toBeFalse();
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

test('aggregateMounts returns empty array when no mounts exist', function (): void {
    $aggregator = new CollectionProgressAggregator;
    $result = $aggregator->aggregateMounts([1, 2, 3]);

    expect($result)->toBe([]);
});
