<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\RateLimitingMiddleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

test('middleware passes request through and returns response', function (): void {
    $middleware = new RateLimitingMiddleware;

    $handler = fn ($request, $options): FulfilledPromise => new FulfilledPromise(new Response(200, [], '{"ok":true}'));

    $wrappedHandler = $middleware($handler);
    $request = new Request('GET', 'https://eu.api.blizzard.com/data/wow/quest/1');
    $promise = $wrappedHandler($request, []);

    $response = $promise->wait();

    expect($response->getStatusCode())->toBe(200);
    expect((string) $response->getBody())->toBe('{"ok":true}');
});

test('middleware allows up to 80 requests within one second', function (): void {
    $middleware = new RateLimitingMiddleware;
    $callCount = 0;

    $handler = function ($request, $options) use (&$callCount): FulfilledPromise {
        $callCount++;

        return new FulfilledPromise(new Response(200));
    };

    $wrappedHandler = $middleware($handler);

    for ($i = 0; $i < 80; $i++) {
        $request = new Request('GET', 'https://eu.api.blizzard.com/test');
        $wrappedHandler($request, []);
    }

    expect($callCount)->toBe(80);
});

test('middleware resolves promise with original response', function (): void {
    $middleware = new RateLimitingMiddleware;
    $originalResponse = new Response(404, ['X-Custom' => 'test'], 'Not Found');

    $handler = fn ($request, $options): FulfilledPromise => new FulfilledPromise($originalResponse);

    $wrappedHandler = $middleware($handler);
    $request = new Request('GET', 'https://eu.api.blizzard.com/missing');
    $promise = $wrappedHandler($request, []);

    $response = $promise->wait();

    expect($response->getStatusCode())->toBe(404);
    expect($response->getHeaderLine('X-Custom'))->toBe('test');
    expect((string) $response->getBody())->toBe('Not Found');
});
