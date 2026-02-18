<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Services\CharacterSeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeoControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function spaReturnsHomePage(): void
    {
        $mock = $this->mock(CharacterSeoService::class);
        /** @var \Mockery\Expectation $exp */
        $exp = $mock->shouldReceive('getHomeMeta');
        $exp->once()->andReturn([
            'title' => 'WowPlanet',
            'description' => 'Test description',
            'ogTitle' => 'WowPlanet',
            'ogDescription' => 'Test',
            'ogImage' => 'https://example.com/image.png',
            'ogUrl' => 'https://example.com',
            'ogType' => 'website',
            'canonicalUrl' => 'https://example.com',
            'jsonLd' => '{}',
        ]);

        $testResponse = $this->get('/');

        $testResponse->assertOk();
    }

    #[Test]
    public function characterPageRedirectsToLowercaseUrl(): void
    {
        $testResponse = $this->get('/character/HYJAL/THRALL');

        $testResponse->assertRedirect('/character/hyjal/thrall');
        $testResponse->assertStatus(301);
    }

    #[Test]
    public function characterPageReturnsViewForValidCharacter(): void
    {
        $mock = $this->mock(CharacterSeoService::class);
        /** @var \Mockery\Expectation $exp */
        $exp = $mock->shouldReceive('getCharacterMeta');
        $exp->once()->with('hyjal', 'thrall')->andReturn([
            'title' => 'Thrall - Hyjal | WowPlanet',
            'description' => 'Test',
            'ogTitle' => 'Thrall',
            'ogDescription' => 'Test',
            'ogImage' => 'https://example.com/avatar.jpg',
            'ogUrl' => 'https://example.com/character/hyjal/thrall',
            'ogType' => 'profile',
            'canonicalUrl' => 'https://example.com/character/hyjal/thrall',
            'jsonLd' => '{}',
        ]);

        $testResponse = $this->get('/character/hyjal/thrall');

        $testResponse->assertOk();
    }

    #[Test]
    public function sitemapReturnsXml(): void
    {
        $mock = $this->mock(CharacterSeoService::class);
        /** @var \Mockery\Expectation $exp */
        $exp = $mock->shouldReceive('generateSitemap');
        $exp->once()->andReturn('<?xml version="1.0"?><urlset></urlset>');

        $testResponse = $this->get('/sitemap.xml');

        $testResponse->assertOk()
            ->assertHeader('Content-Type', 'application/xml');
    }

    #[Test]
    public function robotsReturnsPlainText(): void
    {
        $testResponse = $this->get('/robots.txt');

        $testResponse->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $content = $testResponse->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('User-agent: *', $content);
        $this->assertStringContainsString('Disallow: /api/', $content);
        $this->assertStringContainsString('Sitemap:', $content);
    }
}
