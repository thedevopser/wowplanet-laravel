<?php

declare(strict_types=1);

use App\Application\Services\DatabaseSeoService;
use App\Models\WowAchievement;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;

function seoKeys(): array
{
    return ['title', 'description', 'ogTitle', 'ogDescription', 'ogImage', 'ogUrl', 'ogType', 'canonicalUrl', 'jsonLd'];
}

test('it returns index meta with counts', function (): void {
    WowMount::factory()->count(2)->create();
    WowAchievement::factory()->count(3)->create();
    WowQuest::factory()->count(4)->create();
    WowPet::factory()->count(1)->create();
    WowDecor::factory()->count(5)->create();
    $profession = WowProfession::factory()->create();
    WowRecipe::factory()->count(2)->create(['profession_id' => $profession->id]);

    $databaseSeoService = resolve(DatabaseSeoService::class);
    $meta = $databaseSeoService->getIndexMeta();

    expect($meta)->toHaveKeys(seoKeys())
        ->and($meta['title'])->toContain('WowPlanet')
        ->and($meta['description'])->toContain('2')
        ->and($meta['description'])->toContain('3')
        ->and($meta['jsonLd'])->toBeString();

    $jsonLd = json_decode((string) $meta['jsonLd'], true);
    expect($jsonLd)->toBeArray()
        ->and($jsonLd['@context'])->toBe('https://schema.org')
        ->and($jsonLd['@type'])->toBe('CollectionPage');
});

test('it returns mounts meta', function (): void {
    WowMount::factory()->count(3)->create(['category' => 'Volantes']);

    $databaseSeoService = resolve(DatabaseSeoService::class);
    $meta = $databaseSeoService->getMountsMeta(null);

    expect($meta)->toHaveKeys(seoKeys())
        ->and($meta['title'])->toContain('Montures')
        ->and($meta['title'])->toContain('3')
        ->and($meta['canonicalUrl'])->toContain('/base-de-donnees/montures');
});

test('it returns mounts meta filtered by category', function (): void {
    WowMount::factory()->count(2)->create(['category' => 'Volantes']);
    WowMount::factory()->create(['category' => 'Terrestres']);

    $databaseSeoService = resolve(DatabaseSeoService::class);
    $meta = $databaseSeoService->getMountsMeta('volantes');

    expect($meta)->toHaveKeys(seoKeys())
        ->and($meta['title'])->toContain('Volantes')
        ->and($meta['description'])->toContain('2')
        ->and($meta['canonicalUrl'])->toContain('/base-de-donnees/montures/volantes');
});

test('it returns achievements meta', function (): void {
    WowAchievement::factory()->count(5)->create(['expansion_id' => 10]);

    $databaseSeoService = resolve(DatabaseSeoService::class);
    $meta = $databaseSeoService->getAchievementsMeta('the-war-within');

    expect($meta)->toHaveKeys(seoKeys())
        ->and($meta['title'])->toContain('The War Within')
        ->and($meta['description'])->toContain('5')
        ->and($meta['canonicalUrl'])->toContain('/base-de-donnees/hauts-faits/the-war-within');
});

test('it returns quests meta with zone', function (): void {
    WowQuest::factory()->count(3)->create([
        'expansion_id' => 10,
        'zone_name' => 'Dornogal',
    ]);

    $databaseSeoService = resolve(DatabaseSeoService::class);
    $meta = $databaseSeoService->getQuestsMeta('the-war-within', 'dornogal');

    expect($meta)->toHaveKeys(seoKeys())
        ->and($meta['title'])->toContain('Dornogal')
        ->and($meta['title'])->toContain('The War Within')
        ->and($meta['description'])->toContain('3')
        ->and($meta['canonicalUrl'])->toContain('/base-de-donnees/quetes/the-war-within/dornogal');
});

test('it returns pets meta', function (): void {
    WowPet::factory()->count(4)->create(['category' => 'Aquatique']);

    $databaseSeoService = resolve(DatabaseSeoService::class);
    $meta = $databaseSeoService->getPetsMeta(null);

    expect($meta)->toHaveKeys(seoKeys())
        ->and($meta['title'])->toContain('Mascottes')
        ->and($meta['title'])->toContain('4')
        ->and($meta['canonicalUrl'])->toContain('/base-de-donnees/mascottes');
});

test('it returns decors meta', function (): void {
    WowDecor::factory()->count(6)->create(['category' => 'Meubles']);

    $databaseSeoService = resolve(DatabaseSeoService::class);
    $meta = $databaseSeoService->getDecorsMeta(null);

    expect($meta)->toHaveKeys(seoKeys())
        ->and($meta['title'])->toContain('corations')
        ->and($meta['title'])->toContain('6')
        ->and($meta['canonicalUrl'])->toContain('/base-de-donnees/decorations');
});

test('it returns professions meta', function (): void {
    $profession = WowProfession::factory()->create(['name_fr' => 'Forge']);
    WowRecipe::factory()->count(10)->create(['profession_id' => $profession->id]);

    $databaseSeoService = resolve(DatabaseSeoService::class);
    $meta = $databaseSeoService->getProfessionsMeta('forge');

    expect($meta)->toHaveKeys(seoKeys())
        ->and($meta['title'])->toContain('Forge')
        ->and($meta['title'])->toContain('10')
        ->and($meta['canonicalUrl'])->toContain('/base-de-donnees/professions/forge');
});

test('it builds valid JSON-LD', function (): void {
    WowMount::factory()->create();

    $databaseSeoService = resolve(DatabaseSeoService::class);
    $meta = $databaseSeoService->getIndexMeta();

    $jsonLd = json_decode((string) $meta['jsonLd'], true);
    expect($jsonLd)->toBeArray()
        ->and($jsonLd)->toHaveKey('@context', 'https://schema.org')
        ->and($jsonLd)->toHaveKey('@type', 'CollectionPage')
        ->and($jsonLd)->toHaveKey('name')
        ->and($jsonLd)->toHaveKey('url')
        ->and($jsonLd)->toHaveKey('inLanguage', 'fr')
        ->and($jsonLd)->toHaveKey('breadcrumb')
        ->and($jsonLd)->toHaveKey('isPartOf');

    expect($jsonLd['breadcrumb']['@type'])->toBe('BreadcrumbList');
});
