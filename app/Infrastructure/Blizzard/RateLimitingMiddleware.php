<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Psr\Http\Message\RequestInterface;

class RateLimitingMiddleware
{
    private const MAX_REQUESTS = 100;

    private const DECAY_SECONDS = 1;

    private const BACKOFF_US = 50_000; // 50ms

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            while (! RateLimiter::attempt('blizzard-api', self::MAX_REQUESTS, fn (): true => true, self::DECAY_SECONDS)) {
                Sleep::usleep(self::BACKOFF_US);
            }

            /** @var PromiseInterface $promise */
            $promise = $handler($request, $options);

            return $promise;
        };
    }
}
