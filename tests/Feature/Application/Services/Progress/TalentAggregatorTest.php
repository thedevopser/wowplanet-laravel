<?php

declare(strict_types=1);

use App\Application\Services\Progress\TalentAggregator;

function makeSpecializationsResponse(array $selectedClassTalents = [], array $selectedSpecTalents = [], array $selectedHeroTalents = [], int $heroTreeId = 60): array
{
    return [
        'active_specialization' => ['name' => 'Fureur', 'id' => 72],
        'specializations' => [
            [
                'specialization' => ['name' => 'Fureur', 'id' => 72],
                'loadouts' => [
                    [
                        'is_active' => true,
                        'selected_class_talents' => $selectedClassTalents,
                        'selected_spec_talents' => $selectedSpecTalents,
                        'selected_hero_talents' => $selectedHeroTalents,
                        'selected_hero_talent_tree' => ['id' => $heroTreeId, 'name' => 'Colosse'],
                    ],
                ],
            ],
        ],
    ];
}

function makeTalentTreeResponse(array $classNodes = [], array $specNodes = [], array $heroTrees = []): array
{
    return [
        'id' => 786,
        'playable_class' => ['name' => 'Guerrier', 'id' => 1],
        'playable_specialization' => ['name' => 'Fureur', 'id' => 72],
        'class_talent_nodes' => $classNodes,
        'spec_talent_nodes' => $specNodes,
        'hero_talent_trees' => $heroTrees,
    ];
}

function makeNode(int $id, int $row, int $col, string $type = 'ACTIVE', array $extraRank = [], array $extra = []): array
{
    $rank = array_merge([
        'rank' => 1,
        'tooltip' => [
            'talent' => ['name' => 'Talent '.$id, 'id' => $id * 10],
            'spell_tooltip' => ['spell' => ['name' => 'Sort '.$id, 'id' => $id * 100]],
        ],
    ], $extraRank);

    return array_merge([
        'id' => $id,
        'node_type' => ['id' => $type === 'CHOICE' ? 2 : ($type === 'PASSIVE' ? 1 : 0), 'type' => $type],
        'ranks' => [$rank],
        'display_row' => $row,
        'display_col' => $col,
        'raw_position_x' => $col * 1000,
        'raw_position_y' => $row * 1000,
    ], $extra);
}

test('aggregate transforms talent tree with selected loadout', function (): void {
    $specResponse = makeSpecializationsResponse(
        selectedClassTalents: [
            ['id' => 1, 'rank' => 1, 'tooltip' => ['talent' => ['id' => 10]]],
            ['id' => 2, 'rank' => 2, 'tooltip' => ['talent' => ['id' => 20]]],
        ],
    );

    $treeResponse = makeTalentTreeResponse(
        classNodes: [
            makeNode(1, 1, 1),
            makeNode(2, 2, 1, extra: ['locked_by' => [1], 'unlocks' => []]),
        ],
    );

    $aggregator = new TalentAggregator;
    $result = $aggregator->aggregate($specResponse, $treeResponse);

    expect($result['spec_name'])->toBe('Fureur')
        ->and($result['spec_id'])->toBe(72)
        ->and($result['class_name'])->toBe('Guerrier')
        ->and($result['class_nodes'])->toHaveCount(2);

    $node1 = collect($result['class_nodes'])->firstWhere('id', 1);
    expect($node1['selected_rank'])->toBe(1)
        ->and($node1['x'])->toBe(1)
        ->and($node1['y'])->toBe(1)
        ->and($node1['type'])->toBe('active')
        ->and($node1['entries'])->toHaveCount(1);

    $node2 = collect($result['class_nodes'])->firstWhere('id', 2);
    expect($node2['selected_rank'])->toBe(2)
        ->and($node2['max_rank'])->toBe(1)
        ->and($node2['locked_by'])->toBe([1]);
});

test('aggregate handles empty loadouts', function (): void {
    $specResponse = [
        'active_specialization' => ['name' => 'Fureur', 'id' => 72],
        'specializations' => [
            [
                'specialization' => ['name' => 'Fureur', 'id' => 72],
                'loadouts' => [],
            ],
        ],
    ];

    $treeResponse = makeTalentTreeResponse(
        classNodes: [makeNode(1, 1, 1)],
    );

    $aggregator = new TalentAggregator;
    $result = $aggregator->aggregate($specResponse, $treeResponse);

    expect($result['class_nodes'])->toHaveCount(1);
    $node = $result['class_nodes'][0];
    expect($node['selected_rank'])->toBe(0);
});

test('aggregate handles choice nodes correctly', function (): void {
    $choiceRank = [
        'rank' => 1,
        'choice_of_tooltips' => [
            [
                'talent' => ['name' => 'Option A', 'id' => 100],
                'spell_tooltip' => ['spell' => ['name' => 'Sort A', 'id' => 1000]],
            ],
            [
                'talent' => ['name' => 'Option B', 'id' => 200],
                'spell_tooltip' => ['spell' => ['name' => 'Sort B', 'id' => 2000]],
            ],
        ],
    ];

    $specResponse = makeSpecializationsResponse(
        selectedClassTalents: [
            ['id' => 5, 'rank' => 1, 'tooltip' => ['talent' => ['id' => 200]]],
        ],
    );

    $treeResponse = makeTalentTreeResponse(
        classNodes: [
            [
                'id' => 5,
                'node_type' => ['id' => 2, 'type' => 'CHOICE'],
                'ranks' => [$choiceRank],
                'display_row' => 3,
                'display_col' => 2,
                'raw_position_x' => 2000,
                'raw_position_y' => 3000,
            ],
        ],
    );

    $aggregator = new TalentAggregator;
    $result = $aggregator->aggregate($specResponse, $treeResponse);

    $node = $result['class_nodes'][0];
    expect($node['type'])->toBe('choice')
        ->and($node['entries'])->toHaveCount(2)
        ->and($node['entries'][0]['selected'])->toBeFalse()
        ->and($node['entries'][1]['selected'])->toBeTrue()
        ->and($node['entries'][1]['name'])->toBe('Option B');
});

test('aggregate transforms hero talent trees', function (): void {
    $specResponse = makeSpecializationsResponse(
        selectedHeroTalents: [
            ['id' => 10, 'rank' => 1, 'tooltip' => ['talent' => ['id' => 100]]],
        ],
        heroTreeId: 60,
    );

    $treeResponse = makeTalentTreeResponse(
        heroTrees: [
            [
                'id' => 60,
                'name' => 'Colosse',
                'hero_talent_nodes' => [makeNode(10, 1, 1), makeNode(11, 2, 1)],
                'playable_specializations' => [['id' => 71], ['id' => 72]],
            ],
            [
                'id' => 61,
                'name' => 'Tonnerre',
                'hero_talent_nodes' => [makeNode(20, 1, 1)],
                'playable_specializations' => [['id' => 72], ['id' => 73]],
            ],
        ],
    );

    $aggregator = new TalentAggregator;
    $result = $aggregator->aggregate($specResponse, $treeResponse);

    expect($result['hero_trees'])->toHaveCount(2);

    $colosse = collect($result['hero_trees'])->firstWhere('id', 60);
    expect($colosse['name'])->toBe('Colosse')
        ->and($colosse['active'])->toBeTrue()
        ->and($colosse['nodes'])->toHaveCount(2);

    $selectedNode = collect($colosse['nodes'])->firstWhere('id', 10);
    expect($selectedNode['selected_rank'])->toBe(1);

    $tonnerre = collect($result['hero_trees'])->firstWhere('id', 61);
    expect($tonnerre['active'])->toBeFalse()
        ->and($tonnerre['nodes'])->toHaveCount(1);
    expect($tonnerre['nodes'][0]['selected_rank'])->toBe(0);
});

test('aggregate filters hero trees by active spec', function (): void {
    // Fury is spec 72. Only trees with spec 72 in playable_specializations should be kept.
    $specResponse = makeSpecializationsResponse(heroTreeId: 60);

    $treeResponse = makeTalentTreeResponse(
        heroTrees: [
            [
                'id' => 60,
                'name' => 'Colosse',
                'hero_talent_nodes' => [makeNode(10, 1, 1)],
                'playable_specializations' => [['id' => 71], ['id' => 72]],
            ],
            [
                'id' => 61,
                'name' => 'Tonnerre',
                'hero_talent_nodes' => [makeNode(20, 1, 1)],
                'playable_specializations' => [['id' => 72], ['id' => 73]],
            ],
            [
                'id' => 62,
                'name' => 'Étranger',
                'hero_talent_nodes' => [makeNode(30, 1, 1)],
                'playable_specializations' => [['id' => 71], ['id' => 73]],
            ],
        ],
    );

    $aggregator = new TalentAggregator;
    $result = $aggregator->aggregate($specResponse, $treeResponse);

    expect($result['hero_trees'])->toHaveCount(2);
    $names = array_column($result['hero_trees'], 'name');
    expect($names)->toContain('Colosse')
        ->and($names)->toContain('Tonnerre')
        ->and($names)->not->toContain('Étranger');
});

test('aggregate skips nodes with empty ranks', function (): void {
    $treeResponse = makeTalentTreeResponse(
        classNodes: [
            [
                'id' => 99,
                'node_type' => ['id' => 0, 'type' => 'ACTIVE'],
                'ranks' => [],
                'display_row' => 1,
                'display_col' => 1,
            ],
            [
                'id' => 100,
                'node_type' => ['id' => 2, 'type' => 'CHOICE'],
                'ranks' => [['rank' => 1]],
                'display_row' => 2,
                'display_col' => 1,
            ],
        ],
    );

    $specResponse = makeSpecializationsResponse();

    $aggregator = new TalentAggregator;
    $result = $aggregator->aggregate($specResponse, $treeResponse);

    expect($result['class_nodes'])->toHaveCount(0);
});
