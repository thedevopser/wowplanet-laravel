<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.blizzard.client_id' => 'test-client-id',
        'services.blizzard.client_secret' => 'test-client-secret',
        'services.blizzard.region' => 'eu',
    ]);
    Cache::flush();
});

// ─── getAccessToken ─────────────────────────────────────────

test('getAccessToken fetches token and caches it', function (): void {
    Http::fake([
        'eu.battle.net/oauth/token' => Http::response([
            'access_token' => 'test-token-abc',
            'token_type' => 'bearer',
            'expires_in' => 86399,
        ]),
    ]);

    $client = new BlizzardApiClient(new Client);

    $token = $client->getAccessToken();

    expect($token)->toBe('test-token-abc');
    expect(Cache::get('blizzard_access_token'))->toBe('test-token-abc');
});

test('getAccessToken returns cached token without HTTP call', function (): void {
    Cache::put('blizzard_access_token', 'cached-token', 3600);
    Http::fake();

    $client = new BlizzardApiClient(new Client);

    expect($client->getAccessToken())->toBe('cached-token');
    Http::assertNothingSent();
});

test('getAccessToken throws on failed response', function (): void {
    Http::fake([
        'eu.battle.net/oauth/token' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    $client = new BlizzardApiClient(new Client);

    $client->getAccessToken();
})->throws(RuntimeException::class, 'Failed to fetch Blizzard access token');

// ─── get ────────────────────────────────────────────────────

test('get sends request with bearer token and namespace', function (): void {
    Cache::put('blizzard_access_token', 'my-token', 3600);

    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['name' => 'Thrall'])),
    ]);

    $guzzle = new Client(['handler' => HandlerStack::create($mockHandler), 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $result = $client->get('data/wow/quest/1');

    expect($result)->toBe(['name' => 'Thrall']);
});

test('get uses custom namespace when provided', function (): void {
    Cache::put('blizzard_access_token', 'my-token', 3600);

    $history = [];
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['data' => true])),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push(\GuzzleHttp\Middleware::history($history));

    $guzzle = new Client(['handler' => $handlerStack, 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $client->get('data/wow/quest/1', ['namespace' => 'static-eu']);

    $request = $history[0]['request'];
    expect($request->getHeaderLine('Battlenet-Namespace'))->toBe('static-eu');
});

// ─── getWithUserToken ───────────────────────────────────────

test('getWithUserToken uses provided user token', function (): void {
    $history = [];
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['characters' => []])),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push(\GuzzleHttp\Middleware::history($history));

    $guzzle = new Client(['handler' => $handlerStack, 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $result = $client->getWithUserToken('profile/user/wow', 'user-oauth-token');

    expect($result)->toBe(['characters' => []]);

    $request = $history[0]['request'];
    expect($request->getHeaderLine('Authorization'))->toBe('Bearer user-oauth-token');
    expect($request->getHeaderLine('Battlenet-Namespace'))->toBe('profile-eu');
});

// ─── getBaseOptions ─────────────────────────────────────────

test('getBaseOptions returns static namespace headers', function (): void {
    Cache::put('blizzard_access_token', 'base-token', 3600);

    $client = new BlizzardApiClient(new Client);

    $options = $client->getBaseOptions();

    expect($options['headers']['Authorization'])->toBe('Bearer base-token');
    expect($options['headers']['Battlenet-Namespace'])->toBe('static-eu');
    expect($options['query']['locale'])->toBe('fr_FR');
    expect($options['query']['namespace'])->toBe('static-eu');
});

// ─── getClient ──────────────────────────────────────────────

test('getClient returns injected Guzzle client', function (): void {
    $guzzle = new Client;
    $client = new BlizzardApiClient($guzzle);

    expect($client->getClient())->toBe($guzzle);
});
