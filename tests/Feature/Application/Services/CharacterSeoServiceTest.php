<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Services;

use App\Application\Services\CharacterSeoService;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Models\CharacterVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterSeoServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function getHomeMetaReturnsCorrectStructure(): void
    {
        $this->mock(BlizzardApiClient::class);

        $characterSeoService = resolve(CharacterSeoService::class);
        $meta = $characterSeoService->getHomeMeta();

        $this->assertArrayHasKey('title', $meta);
        $this->assertArrayHasKey('description', $meta);
        $this->assertArrayHasKey('ogTitle', $meta);
        $this->assertArrayHasKey('ogImage', $meta);
        $this->assertArrayHasKey('canonicalUrl', $meta);
        $this->assertArrayHasKey('jsonLd', $meta);
        $this->assertStringContainsString('WowPlanet', (string) $meta['title']);
    }

    #[Test]
    public function getCharacterMetaReturnsCachedData(): void
    {
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

        $this->assertStringContainsString('Thrall', (string) $meta['title']);
        $this->assertSame('profile', $meta['ogType']);
        $this->assertNotNull($meta['jsonLd']);
    }

    #[Test]
    public function getCharacterMetaHandlesApiError(): void
    {
        $mock = $this->mock(BlizzardApiClient::class);

        /** @var \Mockery\Expectation $exp */
        $exp = $mock->shouldReceive('get');
        $exp->andThrow(new \Exception('API Error'));

        $characterSeoService = resolve(CharacterSeoService::class);

        Cache::flush();
        $meta = $characterSeoService->getCharacterMeta('hyjal', 'unknown');

        $this->assertStringContainsString('Unknown', (string) $meta['title']);
        $this->assertNull($meta['jsonLd']);
    }

    #[Test]
    public function generateSitemapReturnsValidXml(): void
    {
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

        $this->assertStringStartsWith('<?xml', $xml);
        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString('/character/hyjal/thrall', $xml);
        $this->assertStringContainsString('/character/dalaran/jaina', $xml);
    }

    #[Test]
    public function getCharacterMetaTracksVisit(): void
    {
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
    }
}
