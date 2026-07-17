<?php

declare(strict_types=1);

use App\Application\Services\CharacterSeoService;

test('spa returns home page', function (): void {
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

    $this->get('/')->assertOk();
});

test('sitemap returns xml', function (): void {
    $mock = $this->mock(CharacterSeoService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('generateSitemapIndex');
    $exp->once()->andReturn('<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>');

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
});

test('robots returns plain text', function (): void {
    $testResponse = $this->get('/robots.txt');

    $testResponse->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    $content = $testResponse->getContent();
    expect($content)->toBeString()
        ->toContain('User-agent: *')
        ->toContain('Disallow: /api/')
        ->toContain('Allow: /character/')
        ->not->toContain('Disallow: /character/')
        ->toContain('Sitemap:');
});

test('faq page returns ok with unique meta', function (): void {
    $this->get('/faq')
        ->assertOk()
        ->assertSee('FAQ')
        ->assertSee('Questions fréquentes');
});

test('cgu page returns ok with unique meta', function (): void {
    $this->get('/cgu')
        ->assertOk()
        ->assertSee('Conditions');
});

test('privacy page returns ok with unique meta', function (): void {
    $this->get('/privacy')
        ->assertOk()
        ->assertSee('Politique de confidentialité');
});
