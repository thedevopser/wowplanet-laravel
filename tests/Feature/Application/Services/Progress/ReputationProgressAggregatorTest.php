<?php

declare(strict_types=1);

use App\Application\Services\Progress\ReputationProgressAggregator;
use App\Infrastructure\Parsers\Db2FactionExpansionMapper;

test('aggregate groups reputations by expansion', function (): void {
    $mock = Mockery::mock(Db2FactionExpansionMapper::class);
    $mock->shouldReceive('build')->andReturn([
        72 => 0,   // Hurlevent → Classic
        1037 => 2, // Chevaliers → WotLK
    ]);
    $mock->shouldReceive('buildMaxRenownMap')->andReturn([]);

    $aggregator = new ReputationProgressAggregator($mock);
    $result = $aggregator->aggregate([
        'reputations' => [
            [
                'faction' => ['id' => 72, 'name' => 'Hurlevent'],
                'standing' => ['name' => 'Exalté', 'tier' => 7, 'value' => 0, 'max' => 0, 'raw' => 42999],
            ],
            [
                'faction' => ['id' => 1037, 'name' => 'Chevaliers de la Lame d\'ébène'],
                'standing' => ['name' => 'Honoré', 'tier' => 5, 'value' => 5000, 'max' => 12000, 'raw' => 14000],
            ],
        ],
    ]);

    expect($result[0]['total'])->toBe(1)
        ->and($result[0]['completed'])->toBe(1)
        ->and($result[0]['factions'])->toHaveCount(1)
        ->and($result[0]['factions'][0]['name'])->toBe('Hurlevent')
        ->and($result[0]['factions'][0]['tier'])->toBe(7);

    expect($result[2]['total'])->toBe(1)
        ->and($result[2]['completed'])->toBe(0)
        ->and($result[2]['factions'][0]['name'])->toBe('Chevaliers de la Lame d\'ébène')
        ->and($result[2]['factions'][0]['value'])->toBe(5000)
        ->and($result[2]['factions'][0]['max'])->toBe(12000);
});

test('aggregate counts exalted tier as completed', function (): void {
    $mock = Mockery::mock(Db2FactionExpansionMapper::class);
    $mock->shouldReceive('build')->andReturn([72 => 0]);
    $mock->shouldReceive('buildMaxRenownMap')->andReturn([]);

    $aggregator = new ReputationProgressAggregator($mock);
    $result = $aggregator->aggregate([
        'reputations' => [
            [
                'faction' => ['id' => 72, 'name' => 'Hurlevent'],
                'standing' => ['name' => 'Exalté', 'tier' => 7, 'value' => 0, 'max' => 0, 'raw' => 42999],
            ],
        ],
    ]);

    expect($result[0]['completed'])->toBe(1)
        ->and($result[0]['factions'][0]['completed'])->toBeTrue();
});

test('aggregate counts max renown as completed via renown_level', function (): void {
    $mock = Mockery::mock(Db2FactionExpansionMapper::class);
    $mock->shouldReceive('build')->andReturn([2503 => 9]);
    $mock->shouldReceive('buildMaxRenownMap')->andReturn([2503 => 25]);

    $aggregator = new ReputationProgressAggregator($mock);
    $result = $aggregator->aggregate([
        'reputations' => [
            [
                'faction' => ['id' => 2503, 'name' => 'Centaure maruuk'],
                'standing' => ['name' => 'Renom 25', 'value' => 0, 'max' => 2500, 'raw' => 62500, 'renown_level' => 25],
            ],
        ],
    ]);

    expect($result[9]['completed'])->toBe(1)
        ->and($result[9]['factions'][0]['completed'])->toBeTrue()
        ->and($result[9]['factions'][0]['renown_level'])->toBe(25);
});

test('aggregate does not count in-progress renown as completed', function (): void {
    $mock = Mockery::mock(Db2FactionExpansionMapper::class);
    $mock->shouldReceive('build')->andReturn([2503 => 9]);
    $mock->shouldReceive('buildMaxRenownMap')->andReturn([2503 => 25]);

    $aggregator = new ReputationProgressAggregator($mock);
    $result = $aggregator->aggregate([
        'reputations' => [
            [
                'faction' => ['id' => 2503, 'name' => 'Centaure maruuk'],
                'standing' => ['name' => 'Renom 15', 'value' => 1200, 'max' => 2500, 'raw' => 37500, 'renown_level' => 15],
            ],
        ],
    ]);

    expect($result[9]['completed'])->toBe(0)
        ->and($result[9]['factions'][0]['completed'])->toBeFalse()
        ->and($result[9]['factions'][0]['renown_level'])->toBe(15);
});

test('aggregate skips factions not in expansion map', function (): void {
    $mock = Mockery::mock(Db2FactionExpansionMapper::class);
    $mock->shouldReceive('build')->andReturn([]);
    $mock->shouldReceive('buildMaxRenownMap')->andReturn([]);

    $aggregator = new ReputationProgressAggregator($mock);
    $result = $aggregator->aggregate([
        'reputations' => [
            [
                'faction' => ['id' => 9999, 'name' => 'Unknown'],
                'standing' => ['name' => 'Neutre', 'tier' => 3, 'value' => 0, 'max' => 3000, 'raw' => 0],
            ],
        ],
    ]);

    // All expansion slots should have 0 factions
    for ($i = 0; $i <= 11; $i++) {
        expect($result[$i]['total'])->toBe(0);
    }
});

test('aggregate returns all 12 expansion slots', function (): void {
    $mock = Mockery::mock(Db2FactionExpansionMapper::class);
    $mock->shouldReceive('build')->andReturn([]);
    $mock->shouldReceive('buildMaxRenownMap')->andReturn([]);

    $aggregator = new ReputationProgressAggregator($mock);
    $result = $aggregator->aggregate(['reputations' => []]);

    expect($result)->toHaveCount(12);
    expect(array_keys($result))->toBe(range(0, 11));
});

test('aggregate handles empty reputations response', function (): void {
    $mock = Mockery::mock(Db2FactionExpansionMapper::class);
    $mock->shouldReceive('build')->andReturn([72 => 0]);
    $mock->shouldReceive('buildMaxRenownMap')->andReturn([]);

    $aggregator = new ReputationProgressAggregator($mock);
    $result = $aggregator->aggregate([]);

    expect($result)->toHaveCount(12);
    expect($result[0]['total'])->toBe(0);
});

test('aggregate preserves faction data fields', function (): void {
    $mock = Mockery::mock(Db2FactionExpansionMapper::class);
    $mock->shouldReceive('build')->andReturn([72 => 0]);
    $mock->shouldReceive('buildMaxRenownMap')->andReturn([]);

    $aggregator = new ReputationProgressAggregator($mock);
    $result = $aggregator->aggregate([
        'reputations' => [
            [
                'faction' => ['id' => 72, 'name' => 'Hurlevent'],
                'standing' => ['name' => 'Révéré', 'tier' => 6, 'value' => 8000, 'max' => 21000, 'raw' => 35000],
            ],
        ],
    ]);

    $faction = $result[0]['factions'][0];
    expect($faction['id'])->toBe(72)
        ->and($faction['name'])->toBe('Hurlevent')
        ->and($faction['standing_name'])->toBe('Révéré')
        ->and($faction['tier'])->toBe(6)
        ->and($faction['value'])->toBe(8000)
        ->and($faction['max'])->toBe(21000)
        ->and($faction['raw'])->toBe(35000)
        ->and($faction['renown_level'])->toBe(0)
        ->and($faction['completed'])->toBeFalse();
});

test('aggregate counts max === 0 with tier > 0 as completed', function (): void {
    $mock = Mockery::mock(Db2FactionExpansionMapper::class);
    $mock->shouldReceive('build')->andReturn([2600 => 10]);
    $mock->shouldReceive('buildMaxRenownMap')->andReturn([]);

    $aggregator = new ReputationProgressAggregator($mock);
    $result = $aggregator->aggregate([
        'reputations' => [
            [
                'faction' => ['id' => 2600, 'name' => 'Council of Dornogal'],
                'standing' => ['name' => 'Amis', 'tier' => 5, 'value' => 0, 'max' => 0, 'raw' => 50000],
            ],
        ],
    ]);

    expect($result[10]['completed'])->toBe(1)
        ->and($result[10]['factions'][0]['completed'])->toBeTrue();
});

test('aggregate handles renown without max renown map entry', function (): void {
    $mock = Mockery::mock(Db2FactionExpansionMapper::class);
    $mock->shouldReceive('build')->andReturn([2503 => 9]);
    $mock->shouldReceive('buildMaxRenownMap')->andReturn([]);

    $aggregator = new ReputationProgressAggregator($mock);
    $result = $aggregator->aggregate([
        'reputations' => [
            [
                'faction' => ['id' => 2503, 'name' => 'Centaure maruuk'],
                'standing' => ['name' => 'Renom 25', 'value' => 0, 'max' => 2500, 'raw' => 62500, 'renown_level' => 25],
            ],
        ],
    ]);

    // Without max renown in map, cannot determine if completed
    expect($result[9]['completed'])->toBe(0)
        ->and($result[9]['factions'][0]['completed'])->toBeFalse();
});
