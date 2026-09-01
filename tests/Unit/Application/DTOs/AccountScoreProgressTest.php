<?php

declare(strict_types=1);

use App\Application\DTOs\AccountScoreProgress;
use App\Application\DTOs\CharacterProfileDTO;

/**
 * @param  list<array{slot: string, category: string|null, total: int, completed: int}>  $appearances
 * @param  list<array<string, mixed>>|null  $raids
 */
function profileWith(string $name, array $appearances = [], ?array $raids = null): CharacterProfileDTO
{
    return new CharacterProfileDTO(
        name: $name,
        realm: 'Hyjal',
        race: 'Orc',
        class: 'Chaman',
        classId: 7,
        level: 80,
        ilvl: 600,
        faction: 'Horde',
        avatarUrl: '',
        classIconUrl: '',
        collections: [],
        mountsCount: 0,
        petsCount: 0,
        appearances: $appearances,
        raids: $raids,
    );
}

/**
 * @param  array<string, list<int>>  $killsByDifficulty
 * @return list<array<string, mixed>>
 */
function raidWith(int $instanceId, int $totalBosses, array $killsByDifficulty): array
{
    $modes = [];
    foreach ($killsByDifficulty as $difficulty => $bossIds) {
        $modes[] = [
            'difficulty_type' => $difficulty,
            'difficulty_label' => $difficulty,
            'completed_count' => count($bossIds),
            'total_count' => $totalBosses,
            'encounters' => array_map(fn (int $id): array => ['id' => $id, 'name' => 'Boss '.$id], $bossIds),
        ];
    }

    return [['instance_id' => $instanceId, 'instance_name' => 'Raid '.$instanceId, 'modes' => $modes]];
}

describe('apparences', function (): void {
    it("retient le meilleur nombre d'apparences par slot", function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall', [
            ['slot' => 'HEAD', 'category' => 'armor', 'total' => 100, 'completed' => 40],
            ['slot' => 'CHEST', 'category' => 'armor', 'total' => 100, 'completed' => 10],
        ]));
        $progress->mergeProfile(profileWith('Jaina', [
            ['slot' => 'HEAD', 'category' => 'armor', 'total' => 100, 'completed' => 25],
            ['slot' => 'CHEST', 'category' => 'armor', 'total' => 100, 'completed' => 60],
        ]));

        expect($progress->buildResult()['appearances'])->toBe([
            ['slot' => 'HEAD', 'category' => 'armor', 'total' => 100, 'completed' => 40],
            ['slot' => 'CHEST', 'category' => 'armor', 'total' => 100, 'completed' => 60],
        ]);
    });

    it('ajoute un slot que seul un personnage connaît', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall', [
            ['slot' => 'HEAD', 'category' => 'armor', 'total' => 100, 'completed' => 40],
        ]));
        $progress->mergeProfile(profileWith('Jaina', [
            ['slot' => 'MAIN_HAND', 'category' => 'weapon', 'total' => 50, 'completed' => 5],
        ]));

        expect($progress->buildResult()['appearances'])->toHaveCount(2);
    });

    it('renvoie une liste vide sans personnage', function (): void {
        expect((new AccountScoreProgress([]))->buildResult()['appearances'])->toBe([]);
    });
});

describe('raids', function (): void {
    it('réunit les boss tués par les différents personnages', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall', [], raidWith(1, 8, ['MYTHIC' => [1, 2]])));
        $progress->mergeProfile(profileWith('Jaina', [], raidWith(1, 8, ['MYTHIC' => [2, 3]])));

        $raids = $progress->buildResult()['raids'];

        expect($raids)->toHaveCount(1)
            ->and($raids[0]['modes'])->toHaveCount(1)
            ->and(array_column($raids[0]['modes'][0]['encounters'], 'id'))->toBe([1, 2, 3])
            ->and($raids[0]['modes'][0]['completed_count'])->toBe(3)
            ->and($raids[0]['modes'][0]['total_count'])->toBe(8);
    });

    it('garde les paliers de difficulté séparés', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall', [], raidWith(1, 4, ['NORMAL' => [1, 2], 'HEROIC' => [1]])));
        $progress->mergeProfile(profileWith('Jaina', [], raidWith(1, 4, ['HEROIC' => [2]])));

        $modes = $progress->buildResult()['raids'][0]['modes'];

        expect(array_column($modes, 'difficulty_type'))->toBe(['NORMAL', 'HEROIC'])
            ->and(array_column($modes[1]['encounters'], 'id'))->toBe([1, 2]);
    });

    it('réunit des raids différents', function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall', [], raidWith(1, 8, ['MYTHIC' => [1]])));
        $progress->mergeProfile(profileWith('Jaina', [], raidWith(2, 6, ['NORMAL' => [10]])));

        expect(array_column($progress->buildResult()['raids'], 'instance_id'))->toBe([1, 2]);
    });

    it("reste nul quand aucun personnage n'a de raid", function (): void {
        $progress = new AccountScoreProgress([]);
        $progress->mergeProfile(profileWith('Thrall'));

        expect($progress->buildResult()['raids'])->toBeNull();
    });
});
