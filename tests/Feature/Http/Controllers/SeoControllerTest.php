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

test('character page redirects to lowercase url', function (): void {
    $this->get('/character/HYJAL/THRALL')
        ->assertRedirect('/character/hyjal/thrall')
        ->assertStatus(301);
});

test('character page returns view for valid character', function (): void {
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

    $this->get('/character/hyjal/thrall')->assertOk();
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
        ->toContain('Sitemap:');
});
