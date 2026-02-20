<?php

declare(strict_types=1);

use App\Application\Services\Progress\ProfessionProgressAggregator;
use App\Models\WowProfession;
use App\Models\WowRecipe;

beforeEach(function (): void {
    $this->profession = WowProfession::factory()->create([
        'id' => 171,
        'name_fr' => 'Alchimie',
        'type' => 'primary',
        'max_skill_levels' => [0 => 300, 10 => 100],
    ]);
});

test('aggregate returns profession progress with expansion breakdown', function (): void {
    WowRecipe::factory()->create(['id' => 1, 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'is_active' => true]);
    WowRecipe::factory()->create(['id' => 2, 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'is_active' => true]);
    WowRecipe::factory()->create(['id' => 3, 'profession_id' => 171, 'expansion_id' => 10, 'category_name' => 'Flacons', 'is_active' => true]);

    $professionResponse = [
        'primaries' => [
            [
                'profession' => ['id' => 171, 'name' => 'Alchemy'],
                'skill_points' => 300,
                'max_skill_points' => 300,
                'tiers' => [
                    [
                        'tier' => ['name' => 'Classic'],
                        'skill_points' => 300,
                        'max_skill_points' => 300,
                        'known_recipes' => [['id' => 1]],
                    ],
                    [
                        'tier' => ['name' => 'Khaz Algar'],
                        'skill_points' => 50,
                        'max_skill_points' => 100,
                        'known_recipes' => [['id' => 3]],
                    ],
                ],
            ],
        ],
        'secondaries' => [],
    ];

    $aggregator = new ProfessionProgressAggregator;
    $result = $aggregator->aggregate($professionResponse, '');

    expect($result)->toHaveCount(1);
    expect($result[0]['profession_id'])->toBe(171);
    expect($result[0]['profession_name'])->toBe('Alchimie');
    expect($result[0]['type'])->toBe('primary');

    // Classic expansion recipes
    expect($result[0]['expansions'][0]['total'])->toBe(2);
    expect($result[0]['expansions'][0]['completed'])->toBe(1);

    // TWW expansion recipes
    expect($result[0]['expansions'][10]['total'])->toBe(1);
    expect($result[0]['expansions'][10]['completed'])->toBe(1);
});

test('aggregate filters recipes by faction', function (): void {
    WowRecipe::factory()->create(['id' => 10, 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'faction' => 'Alliance', 'is_active' => true]);
    WowRecipe::factory()->create(['id' => 11, 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'faction' => 'Horde', 'is_active' => true]);
    WowRecipe::factory()->create(['id' => 12, 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'faction' => null, 'is_active' => true]);

    $professionResponse = [
        'primaries' => [
            [
                'profession' => ['id' => 171, 'name' => 'Alchemy'],
                'skill_points' => 0,
                'max_skill_points' => 0,
                'tiers' => [],
            ],
        ],
        'secondaries' => [],
    ];

    $aggregator = new ProfessionProgressAggregator;
    $result = $aggregator->aggregate($professionResponse, 'Alliance');

    // Alliance sees Alliance + null recipes only (2, not Horde)
    expect($result[0]['expansions'][0]['total'])->toBe(2);
});

test('deduplicateRankedRecipes keeps completed version of same-name recipes', function (): void {
    WowRecipe::factory()->create(['id' => 100, 'name_fr' => 'Potion de soin', 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'is_active' => true]);
    WowRecipe::factory()->create(['id' => 101, 'name_fr' => 'Potion de soin', 'profession_id' => 171, 'expansion_id' => 0, 'category_name' => 'Potions', 'is_active' => true]);

    $professionResponse = [
        'primaries' => [
            [
                'profession' => ['id' => 171, 'name' => 'Alchemy'],
                'skill_points' => 0,
                'max_skill_points' => 0,
                'tiers' => [
                    [
                        'tier' => ['name' => 'Classic'],
                        'skill_points' => 0,
                        'max_skill_points' => 300,
                        'known_recipes' => [['id' => 100]], // Only older rank completed
                    ],
                ],
            ],
        ],
        'secondaries' => [],
    ];

    $aggregator = new ProfessionProgressAggregator;
    $result = $aggregator->aggregate($professionResponse, '');

    // Deduplication: 2 recipes same name → 1 entry, completed (since ID 100 is completed)
    expect($result[0]['expansions'][0]['total'])->toBe(1);
    expect($result[0]['expansions'][0]['completed'])->toBe(1);
});

test('aggregate handles secondary professions', function (): void {
    $cooking = WowProfession::factory()->create([
        'id' => 185,
        'name_fr' => 'Cuisine',
        'type' => 'secondary',
        'max_skill_levels' => [0 => 300],
    ]);

    WowRecipe::factory()->create(['id' => 200, 'profession_id' => 185, 'expansion_id' => 0, 'category_name' => 'Plats', 'is_active' => true]);

    $professionResponse = [
        'primaries' => [],
        'secondaries' => [
            [
                'profession' => ['id' => 185, 'name' => 'Cooking'],
                'skill_points' => 100,
                'max_skill_points' => 300,
                'tiers' => [],
            ],
        ],
    ];

    $aggregator = new ProfessionProgressAggregator;
    $result = $aggregator->aggregate($professionResponse, '');

    expect($result)->toHaveCount(1);
    expect($result[0]['profession_id'])->toBe(185);
    expect($result[0]['type'])->toBe('secondary');
});
