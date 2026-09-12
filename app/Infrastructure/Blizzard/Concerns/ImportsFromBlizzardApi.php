<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Concerns;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\EachPromise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Psr\Http\Message\ResponseInterface;

trait ImportsFromBlizzardApi
{
    private const RATE_LIMIT_WAIT_S = 10;

    private const MAX_RETRIES = 5;

    private const DEFAULT_CONCURRENCY = 20;

    private const NOT_MODIFIED = 304;

    /** Max retries for 429 rate-limit errors (with exponential backoff). */
    private const MAX_RATE_LIMIT_RETRIES = 3;

    /** Max retries for server errors (504/500/timeout) — Blizzard is broken, no point insisting. */
    private const MAX_SERVER_ERROR_RETRIES = 1;

    private readonly BlizzardApiClient $blizzardApiClient;

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchWithRetry(string $endpoint, int $attempt = 1): ?array
    {
        try {
            /** @var string $region */
            $region = config('services.blizzard.region', 'eu');

            return $this->blizzardApiClient->get($endpoint, [
                'namespace' => 'static-'.$region,
            ]);
        } catch (\Exception $exception) {
            $message = $exception->getMessage();

            if (str_contains($message, '404')) {
                return null;
            }

            if ($attempt <= self::MAX_RETRIES && $this->isRetryableError($message)) {
                $delay = self::RATE_LIMIT_WAIT_S * $attempt;
                $this->info(sprintf('Retryable error, waiting %ds (attempt %d/%d)...', $delay, $attempt, self::MAX_RETRIES));
                Sleep::sleep($delay);

                return $this->fetchWithRetry($endpoint, $attempt + 1);
            }

            Log::warning(sprintf('API error [%s]: ', $endpoint).$message);

            return null;
        }
    }

    /**
     * Fetch multiple endpoints concurrently using Guzzle promises.
     *
     * Rate-limit errors (429) are retried with exponential backoff.
     * Server errors (504/500/timeout) are retried once then abandoned.
     *
     * @param  array<string|int, string>  $endpoints  [key => endpoint_url]
     * @param  positive-int|null  $concurrency  Requêtes en vol, par défaut celle de la configuration
     * @return array<string|int, array<string, mixed>|null> [key => response_data|null]
     */
    protected function fetchBatchAsync(array $endpoints, ?int $concurrency = null): array
    {
        /** @var string $region */
        $region = config('services.blizzard.region', 'eu');
        $namespace = 'static-'.$region;

        /** @var int $configuredConcurrency */
        $configuredConcurrency = config('services.blizzard.import_concurrency', self::DEFAULT_CONCURRENCY);
        $concurrency ??= max(1, $configuredConcurrency);

        $results = [];
        $stats = ['ok' => 0, 'not_found' => 0, 'not_modified' => 0, 'timeout' => 0, 'error' => 0];
        $pending = $endpoints;

        /** @var array<string|int, int> $serverErrorCounts Track per-endpoint server error retries */
        $serverErrorCounts = [];

        $maxAttempts = self::MAX_RATE_LIMIT_RETRIES;

        for ($attempt = 0; $attempt <= $maxAttempts; $attempt++) {
            $currentConcurrency = $attempt === 0 ? $concurrency : max(5, intdiv($concurrency, 2 ** $attempt));

            if ($attempt > 0) {
                $delay = self::RATE_LIMIT_WAIT_S * (2 ** ($attempt - 1));
                $this->info(sprintf('  Retrying %d failed requests (attempt %d/%d, waiting %ds, concurrency stepped down to %d)...', count($pending), $attempt, $maxAttempts, $delay, $currentConcurrency));
                Sleep::sleep($delay);
            }

            $failed = [];

            /** @var array<string|int, array{state: string, value?: \Psr\Http\Message\ResponseInterface, reason?: \Throwable}> $settled */
            $settled = $this->settlePool($pending, $namespace, $currentConcurrency);

            foreach ($settled as $key => $result) {
                if ($result['state'] === 'fulfilled' && isset($result['value'])) {
                    $response = $result['value'];
                    $statusCode = $response->getStatusCode();

                    // Un 304 a un corps vide : sans cette sortie il tomberait dans le
                    // décodage JSON, lèverait, serait compté en échec puis retenté.
                    if ($statusCode === self::NOT_MODIFIED) {
                        $results[$key] = null;
                        $stats['not_modified']++;

                        continue;
                    }

                    if ($statusCode >= 400) {
                        if ($this->shouldRetryInBatch($key, $statusCode, $serverErrorCounts)) {
                            $failed[$key] = $pending[$key];
                        } else {
                            $results[$key] = null;
                            $stats['error']++;
                        }

                        Log::debug(sprintf('Async API error [%s]: HTTP %d', $pending[$key] ?? '?', $statusCode));

                        continue;
                    }

                    try {
                        /** @var array<string, mixed> $decoded */
                        $decoded = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException $e) {
                        if ($this->shouldRetryInBatch($key, 0, $serverErrorCounts)) {
                            $failed[$key] = $pending[$key];
                        } else {
                            $results[$key] = null;
                            $stats['error']++;
                        }

                        Log::debug(sprintf('Async API error [%s]: invalid JSON (%s)', $pending[$key] ?? '?', $e->getMessage()));

                        continue;
                    }

                    $results[$key] = $decoded;
                    $stats['ok']++;

                    continue;
                }

                $reason = $result['reason'] ?? null;

                if ($this->isNotFoundError($reason)) {
                    $results[$key] = null;
                    $stats['not_found']++;

                    continue;
                }

                $errorCode = $this->extractStatusCode($reason);
                if ($this->shouldRetryInBatch($key, $errorCode, $serverErrorCounts)) {
                    $failed[$key] = $pending[$key];
                } else {
                    $results[$key] = null;
                    $stats['error']++;
                }

                Log::debug(sprintf('Async API error [%s]: %s', $pending[$key] ?? '?', $reason instanceof \Throwable ? $reason->getMessage() : 'unknown'));
            }

            $pending = $failed;

            if ($pending === []) {
                break;
            }
        }

        foreach (array_keys($pending) as $key) {
            $results[$key] = null;
            $stats['error']++;
        }

        $total = count($endpoints);
        $this->info(sprintf(
            '  API batch: %d/%d OK, %d unchanged, %d not found, %d errors',
            $stats['ok'],
            $total,
            $stats['not_modified'],
            $stats['not_found'],
            $stats['error'],
        ));

        return $results;
    }

    /**
     * Un pool à concurrence constante : dès qu'une requête se termine, la suivante part,
     * au lieu d'attendre le traînard de chaque lot. La régulation par seconde reste au
     * middleware, seul endroit où elle est correcte.
     *
     * @param  array<string|int, string>  $endpoints
     * @param  positive-int  $concurrency
     * @return array<string|int, array{state: string, value?: \Psr\Http\Message\ResponseInterface, reason?: \Throwable}>
     */
    private function settlePool(array $endpoints, string $namespace, int $concurrency): array
    {
        $settled = [];

        $promises = function () use ($endpoints, $namespace): \Generator {
            foreach ($endpoints as $key => $endpoint) {
                yield $key => $this->blizzardApiClient->getAsync($endpoint, [
                    'namespace' => $namespace,
                ]);
            }
        };

        $eachPromise = new EachPromise($promises(), [
            'concurrency' => $concurrency,
            'fulfilled' => function (ResponseInterface $response, string|int $key) use (&$settled): void {
                $settled[$key] = ['state' => 'fulfilled', 'value' => $response];
            },
            'rejected' => function (mixed $reason, string|int $key) use (&$settled): void {
                $settled[$key] = ['state' => 'rejected', 'reason' => $reason instanceof \Throwable ? $reason : new \RuntimeException('unknown rejection')];
            },
        ]);

        $eachPromise->promise()->wait();

        return $settled;
    }

    /**
     * Decide whether a failed request should be retried.
     *
     * 429 (rate limit) → always retry (handled by the outer attempt loop).
     * 500/504/timeout → retry up to MAX_SERVER_ERROR_RETRIES then give up.
     *
     * @param  array<string|int, int>  $serverErrorCounts
     */
    private function shouldRetryInBatch(string|int $key, int $statusCode, array &$serverErrorCounts): bool
    {
        // 429 rate-limit: always worth retrying after backoff
        if ($statusCode === 429) {
            return true;
        }

        // Server errors / timeouts / JSON errors: limited retries
        $serverErrorCounts[$key] = ($serverErrorCounts[$key] ?? 0) + 1;

        return $serverErrorCounts[$key] <= self::MAX_SERVER_ERROR_RETRIES;
    }

    /**
     * Extract HTTP status code from a Guzzle exception.
     */
    private function extractStatusCode(mixed $reason): int
    {
        if ($reason instanceof RequestException && $reason->getResponse() instanceof \Psr\Http\Message\ResponseInterface) {
            return $reason->getResponse()->getStatusCode();
        }

        if ($reason instanceof \Throwable && str_contains($reason->getMessage(), '429')) {
            return 429;
        }

        return 0;
    }

    private function isRetryableError(string $message): bool
    {
        return str_contains($message, '429')
            || str_contains($message, '500')
            || str_contains($message, 'timed out')
            || str_contains($message, 'cURL error');
    }

    private function isNotFoundError(mixed $reason): bool
    {
        if ($reason instanceof RequestException && $reason->getResponse() instanceof \Psr\Http\Message\ResponseInterface) {
            return $reason->getResponse()->getStatusCode() === 404;
        }

        if ($reason instanceof \Throwable) {
            return str_contains($reason->getMessage(), '404');
        }

        return false;
    }

    /**
     * Supprime les lignes locales absentes du lot qui vient d'être importé.
     *
     * L'upsert des importers n'efface jamais rien : sans ce balayage, un item retiré
     * du catalogue (contenu non encore live, entrée non curée, reliquat d'un ancien
     * format d'import) resterait en base indéfiniment. Les appelants doivent avoir
     * interrompu l'import avant d'arriver ici si l'API a répondu de façon dégradée,
     * sans quoi cette suppression détruirait le catalogue.
     *
     * @param  class-string<Model>  $modelClass
     * @param  list<int>  $keptIds
     */
    protected function deleteRowsOutsideCatalog(string $modelClass, array $keptIds, string $label): int
    {
        /** @var list<int> $existingIds */
        $existingIds = $modelClass::query()->pluck('id')->all();

        $staleIds = array_values(array_diff($existingIds, $keptIds));
        if ($staleIds === []) {
            return 0;
        }

        foreach (array_chunk($staleIds, 500) as $chunk) {
            $modelClass::query()->whereIn('id', $chunk)->delete();
        }

        $this->info(sprintf('  %d %s deleted (no longer in catalog).', count($staleIds), $label));

        return count($staleIds);
    }

    protected function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }

        Log::info($message);
    }
}
