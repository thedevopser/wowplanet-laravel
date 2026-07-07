<?php

declare(strict_types=1);

use App\Models\WowAchievement;
use App\Models\WowAppearance;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use Illuminate\Routing\Middleware\ThrottleRequests;

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
});

test('it returns counts for all collections', function (): void {
    WowMount::factory()->count(2)->create();
    WowAchievement::factory()->count(3)->create();
    WowQuest::factory()->count(4)->create();
    WowPet::factory()->count(1)->create();
    WowDecor::factory()->count(5)->create();
    $profession = WowProfession::factory()->create();
    WowRecipe::factory()->count(2)->create(['profession_id' => $profession->id]);

    $response = $this->getJson('/api/database/counts');

    $response->assertOk()
        ->assertJson([
            'mounts' => 2,
            'achievements' => 3,
            'quests' => 4,
            'pets' => 1,
            'decors' => 5,
            'professions' => 1,
            'recipes' => 2,
        ]);
});

test('it counts only active appearances', function (): void {
    WowAppearance::factory()->count(3)->create(['is_active' => true]);
    WowAppearance::factory()->count(2)->create(['is_active' => false]);

    $response = $this->getJson('/api/database/counts');

    $response->assertOk()->assertJson(['appearances' => 3]);
});

test('it returns active appearances paginated with slots', function (): void {
    WowAppearance::factory()->count(2)->create(['slot' => 'HEAD', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->count(3)->create(['slot' => 'WEAPON', 'category' => 'Arme', 'is_active' => true]);
    WowAppearance::factory()->create(['slot' => 'HEAD', 'is_active' => false]); // exclu

    $response = $this->getJson('/api/database/appearances?per_page=2');

    $response->assertOk();

    $data = $response->json();
    expect($data['items'])->toHaveCount(2)
        ->and($data['total'])->toBe(5)
        ->and($data['last_page'])->toBe(3)
        ->and($data['current_page'])->toBe(1);

    $slotSlugs = array_column($data['slots'], 'slug');
    expect($slotSlugs)->toContain('head')->toContain('weapon');
});

test('it filters appearances by slot slug', function (): void {
    WowAppearance::factory()->count(2)->create(['slot' => 'HEAD', 'name_fr' => 'Casque', 'is_active' => true]);
    WowAppearance::factory()->create(['slot' => 'WEAPON', 'name_fr' => 'Épée', 'is_active' => true]);

    $response = $this->getJson('/api/database/appearances?slot=head');

    $response->assertOk();

    $data = $response->json();
    expect($data['items'])->toHaveCount(2)
        ->and($data['total'])->toBe(2);

    $names = array_column($data['items'], 'name_fr');
    expect($names)->not->toContain('Épée');
});

test('it filters appearances by quality and search', function (): void {
    WowAppearance::factory()->create(['name_fr' => 'Casque épique', 'quality' => 4, 'is_active' => true]);
    WowAppearance::factory()->create(['name_fr' => 'Casque rare', 'quality' => 3, 'is_active' => true]);
    WowAppearance::factory()->create(['name_fr' => 'Bottes épiques', 'quality' => 4, 'is_active' => true]);

    $byQuality = $this->getJson('/api/database/appearances?quality=4')->json();
    expect($byQuality['total'])->toBe(2);

    $bySearch = $this->getJson('/api/database/appearances?search=Casque')->json();
    expect($bySearch['total'])->toBe(2);

    $combined = $this->getJson('/api/database/appearances?quality=4&search=Casque')->json();
    expect($combined['total'])->toBe(1);
});

test('it returns mounts with categories', function (): void {
    WowMount::factory()->create(['category' => 'Volantes', 'name_fr' => 'Griffon']);
    WowMount::factory()->create(['category' => 'Volantes', 'name_fr' => 'Hippogriffe']);
    WowMount::factory()->create(['category' => 'Terrestres', 'name_fr' => 'Cheval']);

    $response = $this->getJson('/api/database/mounts');

    $response->assertOk();

    $data = $response->json();
    expect($data['items'])->toHaveCount(3)
        ->and($data['categories'])->toHaveCount(2)
        ->and($data['total'])->toBe(3);

    $categorySlugs = array_column($data['categories'], 'slug');
    expect($categorySlugs)->toContain('terrestres')
        ->toContain('volantes');
});

test('it filters mounts by category slug', function (): void {
    WowMount::factory()->create(['category' => 'Volantes', 'name_fr' => 'Griffon']);
    WowMount::factory()->create(['category' => 'Volantes', 'name_fr' => 'Hippogriffe']);
    WowMount::factory()->create(['category' => 'Terrestres', 'name_fr' => 'Cheval']);

    $response = $this->getJson('/api/database/mounts?category=volantes');

    $response->assertOk();

    $data = $response->json();
    expect($data['items'])->toHaveCount(2)
        ->and($data['total'])->toBe(3);

    $names = array_column($data['items'], 'name_fr');
    expect($names)->toContain('Griffon')
        ->toContain('Hippogriffe')
        ->not->toContain('Cheval');
});

test('it returns achievements with expansions', function (): void {
    WowAchievement::factory()->count(2)->create(['expansion_id' => 0]);
    WowAchievement::factory()->count(3)->create(['expansion_id' => 10]);

    $response = $this->getJson('/api/database/achievements');

    $response->assertOk();

    $data = $response->json();
    expect($data['items'])->toHaveCount(5)
        ->and($data['total'])->toBe(5);

    $expansionSlugs = array_column($data['expansions'], 'slug');
    expect($expansionSlugs)->toContain('classic')
        ->toContain('the-war-within');
});

test('it filters achievements by expansion slug', function (): void {
    WowAchievement::factory()->count(2)->create(['expansion_id' => 0]);
    WowAchievement::factory()->count(3)->create(['expansion_id' => 10]);

    $response = $this->getJson('/api/database/achievements?expansion=the-war-within');

    $response->assertOk();

    $data = $response->json();
    expect($data['items'])->toHaveCount(3)
        ->and($data['total'])->toBe(3);
});

test('it returns quests filtered by expansion', function (): void {
    WowQuest::factory()->count(2)->create(['expansion_id' => 10, 'zone_name' => 'Dornogal']);
    WowQuest::factory()->create(['expansion_id' => 10, 'zone_name' => 'Azj-Kahet']);
    WowQuest::factory()->create(['expansion_id' => 0, 'zone_name' => 'Durotar']);

    $response = $this->getJson('/api/database/quests?expansion=the-war-within');

    $response->assertOk();

    $data = $response->json();
    expect($data['items'])->toHaveCount(3)
        ->and($data['total'])->toBe(3);
});

test('it returns pets with categories', function (): void {
    WowPet::factory()->create(['category' => 'Aquatique', 'name_fr' => 'Tortue']);
    WowPet::factory()->create(['category' => 'Aquatique', 'name_fr' => 'Poisson']);
    WowPet::factory()->create(['category' => 'Volant', 'name_fr' => 'Perroquet']);

    $response = $this->getJson('/api/database/pets');

    $response->assertOk();

    $data = $response->json();
    expect($data['items'])->toHaveCount(3)
        ->and($data['categories'])->toHaveCount(2)
        ->and($data['total'])->toBe(3);
});

test('it returns decors with categories', function (): void {
    WowDecor::factory()->create(['category' => 'Meubles', 'name_fr' => 'Chaise']);
    WowDecor::factory()->create(['category' => 'Meubles', 'name_fr' => 'Table']);
    WowDecor::factory()->create(['category' => 'Luminaires', 'name_fr' => 'Lampe']);

    $response = $this->getJson('/api/database/decors');

    $response->assertOk();

    $data = $response->json();
    expect($data['items'])->toHaveCount(3)
        ->and($data['categories'])->toHaveCount(2)
        ->and($data['total'])->toBe(3);
});

test('it returns professions with recipe counts', function (): void {
    $profession = WowProfession::factory()->create(['name_fr' => 'Forge']);
    WowRecipe::factory()->count(5)->create(['profession_id' => $profession->id]);

    $otherProfession = WowProfession::factory()->create(['name_fr' => 'Alchimie']);
    WowRecipe::factory()->count(3)->create(['profession_id' => $otherProfession->id]);

    $response = $this->getJson('/api/database/professions');

    $response->assertOk();

    $data = $response->json();
    expect($data['professions'])->toHaveCount(2)
        ->and($data['total_professions'])->toBe(2)
        ->and($data['total_recipes'])->toBe(8);

    $forge = collect($data['professions'])->firstWhere('name_fr', 'Forge');
    expect($forge['recipe_count'])->toBe(5)
        ->and($forge['slug'])->toBe('forge');
});

test('it returns profession recipes filtered by profession slug', function (): void {
    $profession = WowProfession::factory()->create(['name_fr' => 'Forge']);
    WowRecipe::factory()->count(3)->create([
        'profession_id' => $profession->id,
        'expansion_id' => 10,
    ]);

    $otherProfession = WowProfession::factory()->create(['name_fr' => 'Alchimie']);
    WowRecipe::factory()->count(2)->create(['profession_id' => $otherProfession->id]);

    $response = $this->getJson('/api/database/professions/recipes?profession=forge');

    $response->assertOk();

    $data = $response->json();
    expect($data['items'])->toHaveCount(3)
        ->and($data['profession']['name_fr'])->toBe('Forge');

    $expansionSlugs = array_column($data['expansions'], 'slug');
    expect($expansionSlugs)->toContain('the-war-within');
});

test('it returns empty when profession slug is missing', function (): void {
    WowProfession::factory()->create(['name_fr' => 'Forge']);

    $response = $this->getJson('/api/database/professions/recipes');

    $response->assertOk();

    $data = $response->json();
    expect($data['items'])->toBeEmpty()
        ->and($data['expansions'])->toBeEmpty();
});
