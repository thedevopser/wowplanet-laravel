<?php

declare(strict_types=1);

use App\Application\Services\Progress\ReputationProgressAggregator;
use App\Infrastructure\Parsers\AddonDataParser;
use App\Infrastructure\Parsers\Db2FactionExpansionMapper;

function makeAggregator(
    array $buildMap = [],
    array $maxRenownMap = [],
    array $namesMap = [],
    array $factionMap = [],
    array $accountWideFactionIds = [],
): ReputationProgressAggregator {
    $mapperMock = Mockery::mock(Db2FactionExpansionMapper::class);
    $mapperMock->shouldReceive('build')->andReturn($buildMap);
    $mapperMock->shouldReceive('buildMaxRenownMap')->andReturn($maxRenownMap);
    $mapperMock->shouldReceive('buildFactionNamesMap')->andReturn($namesMap);
    $mapperMock->shouldReceive('buildAccountWideFactionIds')->andReturn($accountWideFactionIds);

    $addonMock = Mockery::mock(AddonDataParser::class);
    $addonMock->shouldReceive('getReputationFactionMap')->andReturn($factionMap);

    return new ReputationProgressAggregator($mapperMock, $addonMock);
}

test('aggregate groups reputations by expansion', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0, 1037 => 2],
        namesMap: [72 => 'Hurlevent', 1037 => 'Chevaliers de la Lame d\'ébène'],
    );

    $result = $reputationProgressAggregator->aggregate([
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
        ->and($result[0]['factions'][0]['tier'])->toBe(7)
        ->and($result[0]['factions'][0]['started'])->toBeTrue();

    expect($result[2]['total'])->toBe(1)
        ->and($result[2]['completed'])->toBe(0)
        ->and($result[2]['factions'][0]['name'])->toBe('Chevaliers de la Lame d\'ébène')
        ->and($result[2]['factions'][0]['value'])->toBe(5000)
        ->and($result[2]['factions'][0]['max'])->toBe(12000)
        ->and($result[2]['factions'][0]['started'])->toBeTrue();
});

test('aggregate counts exalted tier as completed', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0],
        namesMap: [72 => 'Hurlevent'],
    );

    $result = $reputationProgressAggregator->aggregate([
        'reputations' => [
            [
                'faction' => ['id' => 72, 'name' => 'Hurlevent'],
                'standing' => ['name' => 'Exalté', 'tier' => 7, 'value' => 0, 'max' => 0, 'raw' => 42999],
            ],
        ],
    ]);

    expect($result[0]['completed'])->toBe(1)
        ->and($result[0]['factions'][0]['completed'])->toBeTrue()
        ->and($result[0]['factions'][0]['started'])->toBeTrue();
});

test('aggregate counts max renown as completed via renown_level', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [2503 => 9],
        maxRenownMap: [2503 => 25],
        namesMap: [2503 => 'Centaure maruuk'],
    );

    $result = $reputationProgressAggregator->aggregate([
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
    $reputationProgressAggregator = makeAggregator(
        buildMap: [2503 => 9],
        maxRenownMap: [2503 => 25],
        namesMap: [2503 => 'Centaure maruuk'],
    );

    $result = $reputationProgressAggregator->aggregate([
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
    $reputationProgressAggregator = makeAggregator();

    $result = $reputationProgressAggregator->aggregate([
        'reputations' => [
            [
                'faction' => ['id' => 9999, 'name' => 'Unknown'],
                'standing' => ['name' => 'Neutre', 'tier' => 3, 'value' => 0, 'max' => 3000, 'raw' => 0],
            ],
        ],
    ]);

    for ($i = 0; $i <= 11; $i++) {
        expect($result[$i]['total'])->toBe(0);
    }
});

test('aggregate returns all 12 expansion slots', function (): void {
    $reputationProgressAggregator = makeAggregator();

    $result = $reputationProgressAggregator->aggregate(['reputations' => []]);

    expect($result)->toHaveCount(12);
    expect(array_keys($result))->toBe(range(0, 11));
});

test('aggregate adds unstarted factions from DB2 map when API response is empty', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0],
        namesMap: [72 => 'Hurlevent'],
    );

    $result = $reputationProgressAggregator->aggregate([]);

    expect($result[0]['total'])->toBe(1)
        ->and($result[0]['completed'])->toBe(0)
        ->and($result[0]['factions'][0]['name'])->toBe('Hurlevent')
        ->and($result[0]['factions'][0]['started'])->toBeFalse()
        ->and($result[0]['factions'][0]['tier'])->toBe(-1)
        ->and($result[0]['factions'][0]['standing_name'])->toBe('Non commencée');
});

test('aggregate preserves faction data fields', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0],
        namesMap: [72 => 'Hurlevent'],
    );

    $result = $reputationProgressAggregator->aggregate([
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
        ->and($faction['completed'])->toBeFalse()
        ->and($faction['started'])->toBeTrue();
});

test('aggregate counts max === 0 with tier > 0 as completed', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [2600 => 10],
        namesMap: [2600 => 'Council of Dornogal'],
    );

    $result = $reputationProgressAggregator->aggregate([
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
    $reputationProgressAggregator = makeAggregator(
        buildMap: [2503 => 9],
        namesMap: [2503 => 'Centaure maruuk'],
    );

    $result = $reputationProgressAggregator->aggregate([
        'reputations' => [
            [
                'faction' => ['id' => 2503, 'name' => 'Centaure maruuk'],
                'standing' => ['name' => 'Renom 25', 'value' => 0, 'max' => 2500, 'raw' => 62500, 'renown_level' => 25],
            ],
        ],
    ]);

    expect($result[9]['completed'])->toBe(0)
        ->and($result[9]['factions'][0]['completed'])->toBeFalse();
});

// ─── New tests: unstarted factions ──────────────────────────

test('aggregate mixes started and unstarted factions in same expansion', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0, 76 => 0],
        namesMap: [72 => 'Hurlevent', 76 => 'Orgrimmar'],
    );

    $result = $reputationProgressAggregator->aggregate([
        'reputations' => [
            [
                'faction' => ['id' => 72, 'name' => 'Hurlevent'],
                'standing' => ['name' => 'Exalté', 'tier' => 7, 'value' => 0, 'max' => 0, 'raw' => 42999],
            ],
        ],
    ]);

    expect($result[0]['total'])->toBe(2)
        ->and($result[0]['completed'])->toBe(1);

    $started = collect($result[0]['factions'])->where('started', true);
    $unstarted = collect($result[0]['factions'])->where('started', false);

    expect($started)->toHaveCount(1)
        ->and($started->first()['name'])->toBe('Hurlevent');
    expect($unstarted)->toHaveCount(1)
        ->and($unstarted->first()['name'])->toBe('Orgrimmar')
        ->and($unstarted->first()['standing_name'])->toBe('Non commencée')
        ->and($unstarted->first()['tier'])->toBe(-1)
        ->and($unstarted->first()['completed'])->toBeFalse();
});

// ─── New tests: faction filtering ───────────────────────────

test('aggregate filters opposite faction reputations for Alliance character', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0, 530 => 0, 1037 => 0],
        namesMap: [72 => 'Hurlevent', 530 => 'Trolls Sombrelance', 1037 => 'Neutres'],
        factionMap: [72 => 'Alliance', 530 => 'Horde'],
    );

    $result = $reputationProgressAggregator->aggregate(['reputations' => []], 'Alliance');

    $names = collect($result[0]['factions'])->pluck('name')->all();
    expect($names)->toContain('Hurlevent')
        ->and($names)->toContain('Neutres')
        ->and($names)->not->toContain('Trolls Sombrelance')
        ->and($result[0]['total'])->toBe(2);
});

test('aggregate filters opposite faction reputations for Horde character', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0, 530 => 0, 1037 => 0],
        namesMap: [72 => 'Hurlevent', 530 => 'Trolls Sombrelance', 1037 => 'Neutres'],
        factionMap: [72 => 'Alliance', 530 => 'Horde'],
    );

    $result = $reputationProgressAggregator->aggregate(['reputations' => []], 'Horde');

    $names = collect($result[0]['factions'])->pluck('name')->all();
    expect($names)->toContain('Trolls Sombrelance')
        ->and($names)->toContain('Neutres')
        ->and($names)->not->toContain('Hurlevent')
        ->and($result[0]['total'])->toBe(2);
});

test('aggregate does not filter when characterFaction is empty', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0, 530 => 0],
        namesMap: [72 => 'Hurlevent', 530 => 'Trolls Sombrelance'],
        factionMap: [72 => 'Alliance', 530 => 'Horde'],
    );

    $result = $reputationProgressAggregator->aggregate(['reputations' => []]);

    expect($result[0]['total'])->toBe(2);
});

test('aggregate keeps opposite faction reputations when started via API', function (): void {
    $reputationProgressAggregator = makeAggregator(
        buildMap: [72 => 0, 530 => 0],
        namesMap: [72 => 'Hurlevent', 530 => 'Trolls Sombrelance'],
        factionMap: [72 => 'Alliance', 530 => 'Horde'],
    );

    $result = $reputationProgressAggregator->aggregate([
        'reputations' => [
            [
                'faction' => ['id' => 530, 'name' => 'Trolls Sombrelance'],
                'standing' => ['name' => 'Neutre', 'tier' => 3, 'value' => 0, 'max' => 3000, 'raw' => 0],
            ],
        ],
    ], 'Alliance');

    $names = collect($result[0]['factions'])->pluck('name')->all();
    // API returned it for this character, so it must be kept even if tagged Horde
    expect($names)->toContain('Trolls Sombrelance')
        ->and($names)->toContain('Hurlevent')
        ->and($result[0]['total'])->toBe(2);
});
