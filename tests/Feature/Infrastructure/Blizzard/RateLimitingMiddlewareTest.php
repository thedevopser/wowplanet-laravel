<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\RateLimitingMiddleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\RateLimiter;

test('middleware passes request through rate limiter and returns response', function (): void {
    RateLimiter::shouldReceive('attempt')
        ->once()
        ->withArgs(fn (string $key, int $maxAttempts, callable $callback, int $perSeconds): bool => $key === 'blizzard-api' && $maxAttempts === 100 && $perSeconds === 1)
        ->andReturnUsing(fn (string $key, int $maxAttempts, callable $callback) => $callback());

    $middleware = new RateLimitingMiddleware;

    $handler = (fn ($request, $options): \GuzzleHttp\Promise\FulfilledPromise => new FulfilledPromise(new Response(200, [], '{"ok":true}')));

    $wrappedHandler = $middleware($handler);
    $request = new Request('GET', 'https://eu.api.blizzard.com/data/wow/quest/1');
    $promise = $wrappedHandler($request, []);

    $response = $promise->wait();

    expect($response->getStatusCode())->toBe(200);
    expect((string) $response->getBody())->toBe('{"ok":true}');
});

test('middleware uses blizzard-api key with 100 requests per second limit', function (): void {
    RateLimiter::shouldReceive('attempt')
        ->once()
        ->withArgs(function (string $key, int $maxAttempts, callable $callback, int $perSeconds): true {
            expect($key)->toBe('blizzard-api');
            expect($maxAttempts)->toBe(100);
            expect($perSeconds)->toBe(1);

            return true;
        })
        ->andReturnUsing(fn (string $key, int $maxAttempts, callable $callback) => $callback());

    $middleware = new RateLimitingMiddleware;

    $handler = fn ($request, $options): \GuzzleHttp\Promise\FulfilledPromise => new FulfilledPromise(new Response(200));

    $wrappedHandler = $middleware($handler);
    $request = new Request('GET', 'https://eu.api.blizzard.com/test');
    $wrappedHandler($request, []);
});

test('middleware resolves promise with original response', function (): void {
    RateLimiter::shouldReceive('attempt')
        ->once()
        ->andReturnUsing(fn (string $key, int $maxAttempts, callable $callback) => $callback());

    $middleware = new RateLimitingMiddleware;
    $originalResponse = new Response(404, ['X-Custom' => 'test'], 'Not Found');

    $handler = fn ($request, $options): \GuzzleHttp\Promise\FulfilledPromise => new FulfilledPromise($originalResponse);

    $wrappedHandler = $middleware($handler);
    $request = new Request('GET', 'https://eu.api.blizzard.com/missing');
    $promise = $wrappedHandler($request, []);

    $response = $promise->wait();

    expect($response->getStatusCode())->toBe(404);
    expect($response->getHeaderLine('X-Custom'))->toBe('test');
    expect((string) $response->getBody())->toBe('Not Found');
});
