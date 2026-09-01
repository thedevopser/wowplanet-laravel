<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Concerns;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Interrogation parallèle d'endpoints de profil Blizzard, avec retries.
 *
 * Les classes utilisatrices exposent un `$this->blizzardApiClient`.
 */
trait FetchesProfileEndpoints
{
    private const MAX_ASYNC_RETRIES = 2;

    private const RETRY_DELAY_S = 3;

    /**
     * @param  array<string, array{endpoint: string, query: array<string, mixed>}>  $endpoints
     * @return array<string, array<string, mixed>>
     */
    private function fetchAsync(array $endpoints): array
    {
        $results = [];
        /** @var array<string, array{endpoint: string, query: array<string, mixed>}> $pending */
        $pending = $endpoints;

        for ($attempt = 0; $attempt <= self::MAX_ASYNC_RETRIES; $attempt++) {
            if ($attempt > 0 && $pending !== []) {
                Log::info(sprintf('Profile API: retrying %d failed requests (attempt %d/%d)', count($pending), $attempt, self::MAX_ASYNC_RETRIES));
                Sleep::usleep(self::RETRY_DELAY_S * 1_000_000);
            }

            $promises = [];
            foreach ($pending as $key => $config) {
                $promises[$key] = $this->blizzardApiClient->getAsync($config['endpoint'], $config['query']);
            }

            /** @var array<string, array{state: string, value?: \Psr\Http\Message\ResponseInterface, reason?: \Throwable}> $settled */
            $settled = Utils::settle($promises)->wait();

            $failed = [];
            foreach ($settled as $key => $result) {
                if ($result['state'] === 'fulfilled' && isset($result['value'])) {
                    /** @var array<string, mixed> $decoded */
                    $decoded = json_decode($result['value']->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                    $result['value']->getBody()->close();
                    unset($settled[$key]);
                    $results[$key] = $decoded;

                    continue;
                }

                $reason = $result['reason'] ?? null;

                // 404 = not found (e.g. decor not unlocked), treat as empty
                if ($reason instanceof RequestException
                    && $reason->getResponse() instanceof \Psr\Http\Message\ResponseInterface
                    && $reason->getResponse()->getStatusCode() === 404) {
                    $results[$key] = [];

                    continue;
                }

                // Retryable: timeout, 500, 504, rate limit
                if ($this->isRetryableError($reason)) {
                    $failed[$key] = $pending[$key];
                    Log::debug(sprintf('Profile API async error [%s]: %s', $pending[$key]['endpoint'], $reason instanceof \Throwable ? $reason->getMessage() : 'unknown'));

                    continue;
                }

                // Non-retryable error, treat as empty
                Log::warning(sprintf('Profile API non-retryable error [%s]: %s', $pending[$key]['endpoint'], $reason instanceof \Throwable ? $reason->getMessage() : 'unknown'));
                $results[$key] = [];
            }

            $pending = $failed;

            if ($pending === []) {
                break;
            }
        }

        // Any remaining failures after retries → empty
        foreach (array_keys($pending) as $key) {
            Log::warning(sprintf('Profile API gave up on [%s] after %d retries', $pending[$key]['endpoint'], self::MAX_ASYNC_RETRIES));
            $results[$key] = [];
        }

        return $results;
    }

    private function isRetryableError(mixed $reason): bool
    {
        if (! $reason instanceof \Throwable) {
            return false;
        }

        $message = $reason->getMessage();

        return str_contains($message, '429')
            || str_contains($message, '500')
            || str_contains($message, '504')
            || str_contains($message, 'timed out')
            || str_contains($message, 'cURL error');
    }
}
