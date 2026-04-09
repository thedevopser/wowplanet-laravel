<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;

test('show returns talent data for character', function (): void {
    // Pre-cache the spell icon so the controller does not attempt getAsync
    \Illuminate\Support\Facades\Cache::put('spell_icon:100', 'https://render.worldofwarcraft.com/eu/icons/56/charge.jpg', 60);
    // Ensure clean talent tree cache
    \Illuminate\Support\Facades\Cache::forget('talent_tree:786:72');
    \Illuminate\Support\Facades\Cache::forget('playable_spec:72');

    $mock = $this->mock(BlizzardApiClient::class);

    // Specializations endpoint
    /** @var \Mockery\Expectation $specExp */
    $specExp = $mock->shouldReceive('get');
    $specExp->once()
        ->with('profile/wow/character/hyjal/thrall/specializations')
        ->andReturn([
            'active_specialization' => ['name' => 'Fureur', 'id' => 72],
            'specializations' => [
                [
                    'specialization' => ['name' => 'Fureur', 'id' => 72],
                    'loadouts' => [
                        [
                            'is_active' => true,
                            'selected_class_talents' => [],
                            'selected_spec_talents' => [],
                            'selected_hero_talents' => [],
                            'selected_hero_talent_tree' => ['id' => 60, 'name' => 'Colosse'],
                        ],
                    ],
                ],
            ],
        ]);

    // Playable specialization (for talentTreeId resolution)
    /** @var \Mockery\Expectation $regionExp */
    $regionExp = $mock->shouldReceive('getRegion');
    $regionExp->andReturn('eu');

    /** @var \Mockery\Expectation $playableExp */
    $playableExp = $mock->shouldReceive('get');
    $playableExp->once()
        ->with('data/wow/playable-specialization/72', ['namespace' => 'static-eu'])
        ->andReturn([
            'spec_talent_tree' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/talent-tree/786/playable-specialization/72']],
        ]);

    // Talent tree structure
    /** @var \Mockery\Expectation $treeExp */
    $treeExp = $mock->shouldReceive('get');
    $treeExp->once()
        ->with('data/wow/talent-tree/786/playable-specialization/72', ['namespace' => 'static-eu'])
        ->andReturn([
            'id' => 786,
            'playable_class' => ['name' => 'Guerrier', 'id' => 1],
            'playable_specialization' => ['name' => 'Fureur', 'id' => 72],
            'class_talent_nodes' => [
                [
                    'id' => 1,
                    'node_type' => ['id' => 0, 'type' => 'ACTIVE'],
                    'ranks' => [['rank' => 1, 'tooltip' => ['talent' => ['name' => 'Charge', 'id' => 10], 'spell_tooltip' => ['spell' => ['name' => 'Charge', 'id' => 100]]]]],
                    'display_row' => 1,
                    'display_col' => 5,
                ],
            ],
            'spec_talent_nodes' => [],
            'hero_talent_trees' => [],
        ]);

    $this->getJson('/api/character/hyjal/thrall/talents')
        ->assertOk()
        ->assertJsonFragment([
            'spec_name' => 'Fureur',
            'class_name' => 'Guerrier',
        ])
        ->assertJsonStructure([
            'spec_name',
            'spec_id',
            'class_name',
            'class_nodes',
            'spec_nodes',
            'hero_trees',
        ]);
});

test('show returns 404 when no active specialization', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $specExp */
    $specExp = $mock->shouldReceive('get');
    $specExp->once()
        ->with('profile/wow/character/hyjal/unknown/specializations')
        ->andReturn([
            'active_specialization' => [],
            'specializations' => [],
        ]);

    $this->getJson('/api/character/hyjal/unknown/talents')
        ->assertNotFound();
});

test('show returns 500 on API error', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $specExp */
    $specExp = $mock->shouldReceive('get');
    $specExp->once()
        ->with('profile/wow/character/hyjal/thrall/specializations')
        ->andThrow(new RuntimeException('API error'));

    $this->getJson('/api/character/hyjal/thrall/talents')
        ->assertStatus(500)
        ->assertJsonStructure(['error']);
});
