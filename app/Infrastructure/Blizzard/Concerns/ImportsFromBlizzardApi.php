<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Concerns;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

trait ImportsFromBlizzardApi
{
    private const RATE_LIMIT_WAIT_S = 10;

    private const MAX_RETRIES = 5;

    private const CONCURRENT_BATCH_SIZE = 20;

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

    private const MAX_BATCH_RETRIES = 5;

    /**
     * Fetch multiple endpoints concurrently using Guzzle promises.
     *
     * @param  array<string|int, string>  $endpoints  [key => endpoint_url]
     * @param  positive-int  $batchSize
     * @return array<string|int, array<string, mixed>|null> [key => response_data|null]
     */
    protected function fetchBatchAsync(array $endpoints, int $batchSize = self::CONCURRENT_BATCH_SIZE): array
    {
        /** @var string $region */
        $region = config('services.blizzard.region', 'eu');
        $namespace = 'static-'.$region;

        $results = [];
        $stats = ['ok' => 0, 'not_found' => 0, 'timeout' => 0, 'error' => 0];
        $pending = $endpoints;

        for ($attempt = 0; $attempt <= self::MAX_BATCH_RETRIES; $attempt++) {
            $currentBatchSize = $attempt === 0 ? $batchSize : max(5, intdiv($batchSize, 2));

            if ($attempt > 0) {
                $delay = self::RATE_LIMIT_WAIT_S / 2 * (2 ** $attempt);
                $this->info(sprintf('  Retrying %d failed requests (attempt %d/%d, waiting %ds, concurrency %d)...', count($pending), $attempt, self::MAX_BATCH_RETRIES, $delay, $currentBatchSize));
                Sleep::sleep($delay);
            }

            $failed = [];

            foreach (array_chunk($pending, $currentBatchSize, true) as $batch) {
                $promises = [];
                foreach ($batch as $key => $endpoint) {
                    $promises[$key] = $this->blizzardApiClient->getAsync($endpoint, [
                        'namespace' => $namespace,
                    ]);
                }

                /** @var array<string|int, array{state: string, value?: \Psr\Http\Message\ResponseInterface, reason?: \Throwable}> $settled */
                $settled = Utils::settle($promises)->wait();

                foreach ($settled as $key => $result) {
                    if ($result['state'] === 'fulfilled' && isset($result['value'])) {
                        /** @var array<string, mixed> $decoded */
                        $decoded = json_decode($result['value']->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
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

                    $failed[$key] = $pending[$key];
                    Log::debug(sprintf('Async API error [%s]: %s', $pending[$key] ?? '?', $reason instanceof \Throwable ? $reason->getMessage() : 'unknown'));
                }
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
            '  API batch: %d/%d OK, %d not found, %d errors',
            $stats['ok'],
            $total,
            $stats['not_found'],
            $stats['error'],
        ));

        return $results;
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

    protected function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }

        Log::info($message);
    }
}
