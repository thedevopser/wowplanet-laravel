<?php

declare(strict_types=1);

use App\Application\Services\CharacterSeoService;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Models\CharacterVisit;
use Illuminate\Support\Facades\Cache;

test('get home meta returns correct structure', function (): void {
    $this->mock(BlizzardApiClient::class);

    $characterSeoService = resolve(CharacterSeoService::class);
    $meta = $characterSeoService->getHomeMeta();

    expect($meta)->toHaveKey('title')
        ->toHaveKey('description')
        ->toHaveKey('ogTitle')
        ->toHaveKey('ogImage')
        ->toHaveKey('canonicalUrl')
        ->toHaveKey('jsonLd')
        ->and((string) $meta['title'])->toContain('WowPlanet');
});

test('get character meta returns cached data', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('get');
    $exp->andReturn([
        'name' => 'Thrall',
        'realm' => ['name' => 'Hyjal'],
        'level' => 80,
        'race' => ['name' => 'Orc'],
        'character_class' => ['name' => 'Chaman'],
        'faction' => ['name' => 'Horde'],
        'equipped_item_level' => 620,
        'assets' => [['key' => 'avatar', 'value' => 'https://example.com/avatar.jpg']],
    ]);

    $characterSeoService = resolve(CharacterSeoService::class);

    Cache::flush();
    $meta = $characterSeoService->getCharacterMeta('hyjal', 'thrall');

    expect((string) $meta['title'])->toContain('Thrall')
        ->and($meta['ogType'])->toBe('profile')
        ->and($meta['jsonLd'])->not->toBeNull();
});

test('get character meta handles api error', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('get');
    $exp->andThrow(new Exception('API Error'));

    $characterSeoService = resolve(CharacterSeoService::class);

    Cache::flush();
    $meta = $characterSeoService->getCharacterMeta('hyjal', 'unknown');

    expect((string) $meta['title'])->toContain('Unknown')
        ->and($meta['jsonLd'])->toBeNull();
});

test('generate sitemap returns valid xml', function (): void {
    CharacterVisit::factory()->create([
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
        'last_visited_at' => now()->subDay(),
    ]);

    CharacterVisit::factory()->create([
        'realm_slug' => 'dalaran',
        'character_name' => 'jaina',
        'last_visited_at' => now()->subDays(2),
    ]);

    $this->mock(BlizzardApiClient::class);

    $characterSeoService = resolve(CharacterSeoService::class);
    $xml = $characterSeoService->generateSitemap();

    expect($xml)->toStartWith('<?xml')
        ->toContain('<urlset')
        ->toContain('/character/hyjal/thrall')
        ->toContain('/character/dalaran/jaina');
});

test('get character meta tracks visit', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('get');
    $exp->andReturn([
        'name' => 'Thrall',
        'realm' => ['name' => 'Hyjal'],
        'level' => 80,
        'race' => ['name' => 'Orc'],
        'character_class' => ['name' => 'Chaman'],
        'faction' => ['name' => 'Horde'],
        'equipped_item_level' => 620,
        'assets' => [],
    ]);

    $characterSeoService = resolve(CharacterSeoService::class);

    Cache::flush();
    $characterSeoService->getCharacterMeta('hyjal', 'thrall');

    $this->assertDatabaseHas('character_visits', [
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
    ]);
});
