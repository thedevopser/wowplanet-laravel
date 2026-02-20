<?php

declare(strict_types=1);

use App\Application\Services\Progress\AchievementProgressAggregator;
use App\Models\WowAchievement;

test('aggregate groups achievements by expansion and category', function (): void {
    WowAchievement::factory()->create(['id' => 1, 'expansion_id' => 0, 'category_name' => 'Général', 'is_active' => true]);
    WowAchievement::factory()->create(['id' => 2, 'expansion_id' => 0, 'category_name' => 'Quêtes', 'is_active' => true]);
    WowAchievement::factory()->create(['id' => 3, 'expansion_id' => 10, 'category_name' => 'Donjons', 'is_active' => true]);

    $aggregator = new AchievementProgressAggregator;
    $result = $aggregator->aggregate([1, 3]);

    // Classic
    expect($result[0]['total'])->toBe(2);
    expect($result[0]['completed'])->toBe(1);
    expect($result[0]['categories'])->toHaveCount(2);

    // TWW
    expect($result[10]['total'])->toBe(1);
    expect($result[10]['completed'])->toBe(1);
});

test('aggregate skips inactive achievements', function (): void {
    WowAchievement::factory()->create(['id' => 10, 'expansion_id' => 0, 'category_name' => 'Général', 'is_active' => true]);
    WowAchievement::factory()->create(['id' => 11, 'expansion_id' => 0, 'category_name' => 'Général', 'is_active' => false]);

    $aggregator = new AchievementProgressAggregator;
    $result = $aggregator->aggregate([]);

    expect($result[0]['total'])->toBe(1);
});

test('aggregate returns all 12 expansion slots', function (): void {
    $aggregator = new AchievementProgressAggregator;
    $result = $aggregator->aggregate([]);

    expect($result)->toHaveCount(12);
    expect(array_keys($result))->toBe(range(0, 11));
});

test('aggregate marks correct items as completed in category', function (): void {
    WowAchievement::factory()->create(['id' => 100, 'expansion_id' => 9, 'category_name' => 'Exploration', 'is_active' => true]);
    WowAchievement::factory()->create(['id' => 101, 'expansion_id' => 9, 'category_name' => 'Exploration', 'is_active' => true]);

    $aggregator = new AchievementProgressAggregator;
    $result = $aggregator->aggregate([100]);

    $exploration = $result[9]['categories'][0];
    expect($exploration['name'])->toBe('Exploration');
    expect($exploration['total'])->toBe(2);
    expect($exploration['completed'])->toBe(1);

    $completedItem = collect($exploration['items'])->firstWhere('id', 100);
    $incompletedItem = collect($exploration['items'])->firstWhere('id', 101);
    expect($completedItem['is_completed'])->toBeTrue();
    expect($incompletedItem['is_completed'])->toBeFalse();
});
