<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitingMiddleware
{
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            /** @var PromiseInterface $result */
            $result = RateLimiter::attempt(
                'blizzard-api',
                100, // Max requests
                function () use ($handler, $request, $options): PromiseInterface {
                    /** @var PromiseInterface $promise */
                    $promise = $handler($request, $options);
                    return $promise->then(
                        fn(ResponseInterface $response): ResponseInterface => $response
                    );
                },
                1 // Per second
            );

            return $result;
        };
    }
}
