<?php

declare(strict_types=1);

use App\Application\DTOs\CrossCharacterProgress;

function makeReputationsPayload(int $factionId, int $renownLevel = 0, int $raw = 0, int $tier = 0, int $max = 1, string $standingName = ''): array
{
    return [
        'questIds' => [],
        'achievementIds' => [],
        'reputations' => [
            'reputations' => [
                [
                    'faction' => ['id' => $factionId],
                    'standing' => [
                        'raw' => $raw,
                        'value' => 0,
                        'max' => $max,
                        'tier' => $tier,
                        'name' => $standingName,
                        'renown_level' => $renownLevel,
                    ],
                ],
            ],
        ],
        'professions' => [],
    ];
}

test('renown vs renown: higher renown wins regardless of merge order', function (): void {
    $progress1 = new CrossCharacterProgress;
    $progress1->mergeCharacter('CharA', makeReputationsPayload(factionId: 2503, renownLevel: 5, raw: 12500));
    $progress1->mergeCharacter('CharB', makeReputationsPayload(factionId: 2503, renownLevel: 9, raw: 22500));

    $progress2 = new CrossCharacterProgress;
    $progress2->mergeCharacter('CharB', makeReputationsPayload(factionId: 2503, renownLevel: 9, raw: 22500));
    $progress2->mergeCharacter('CharA', makeReputationsPayload(factionId: 2503, renownLevel: 5, raw: 12500));

    expect($progress1->bestFactionStandings[2503]['character_name'])->toBe('CharB')
        ->and($progress1->bestFactionStandings[2503]['renown_level'])->toBe(9)
        ->and($progress2->bestFactionStandings[2503]['character_name'])->toBe('CharB')
        ->and($progress2->bestFactionStandings[2503]['renown_level'])->toBe(9);
});

test('renown vs traditional with high raw: renown wins regardless of merge order', function (): void {
    $progress1 = new CrossCharacterProgress;
    $progress1->mergeCharacter('RenownChar', makeReputationsPayload(factionId: 2503, renownLevel: 9, raw: 10000));
    $progress1->mergeCharacter('LegacyChar', makeReputationsPayload(factionId: 2503, renownLevel: 0, raw: 42000, tier: 6));

    $progress2 = new CrossCharacterProgress;
    $progress2->mergeCharacter('LegacyChar', makeReputationsPayload(factionId: 2503, renownLevel: 0, raw: 42000, tier: 6));
    $progress2->mergeCharacter('RenownChar', makeReputationsPayload(factionId: 2503, renownLevel: 9, raw: 10000));

    expect($progress1->bestFactionStandings[2503]['character_name'])->toBe('RenownChar')
        ->and($progress1->bestFactionStandings[2503]['renown_level'])->toBe(9)
        ->and($progress2->bestFactionStandings[2503]['character_name'])->toBe('RenownChar')
        ->and($progress2->bestFactionStandings[2503]['renown_level'])->toBe(9);
});

test('traditional vs traditional: higher raw wins regardless of merge order', function (): void {
    $progress1 = new CrossCharacterProgress;
    $progress1->mergeCharacter('CharA', makeReputationsPayload(factionId: 72, raw: 30000, tier: 5));
    $progress1->mergeCharacter('CharB', makeReputationsPayload(factionId: 72, raw: 42000, tier: 7));

    $progress2 = new CrossCharacterProgress;
    $progress2->mergeCharacter('CharB', makeReputationsPayload(factionId: 72, raw: 42000, tier: 7));
    $progress2->mergeCharacter('CharA', makeReputationsPayload(factionId: 72, raw: 30000, tier: 5));

    expect($progress1->bestFactionStandings[72]['character_name'])->toBe('CharB')
        ->and($progress1->bestFactionStandings[72]['raw'])->toBe(42000)
        ->and($progress2->bestFactionStandings[72]['character_name'])->toBe('CharB')
        ->and($progress2->bestFactionStandings[72]['raw'])->toBe(42000);
});

test('unstarted character does not overwrite renown standing', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('MainChar', makeReputationsPayload(factionId: 2503, renownLevel: 9, raw: 22500));
    $progress->mergeCharacter('AltChar', makeReputationsPayload(factionId: 2503, renownLevel: 0, raw: 0));

    expect($progress->bestFactionStandings[2503]['character_name'])->toBe('MainChar')
        ->and($progress->bestFactionStandings[2503]['renown_level'])->toBe(9);
});

test('equal renown does not overwrite existing entry', function (): void {
    $progress = new CrossCharacterProgress;
    $progress->mergeCharacter('FirstChar', makeReputationsPayload(factionId: 2503, renownLevel: 9, raw: 22500));
    $progress->mergeCharacter('SecondChar', makeReputationsPayload(factionId: 2503, renownLevel: 9, raw: 22500));

    expect($progress->bestFactionStandings[2503]['character_name'])->toBe('FirstChar');
});
