<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Concerns;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

trait ImportsFromBlizzardApi
{
    private const REQUEST_DELAY_MS = 350;

    private const ICON_REQUEST_DELAY_MS = 500;

    private const RATE_LIMIT_WAIT_S = 10;

    private const MAX_RETRIES = 5;

    private readonly BlizzardApiClient $blizzardApiClient;

    protected function delayRequest(): void
    {
        Sleep::usleep(self::REQUEST_DELAY_MS * 1000);
    }

    protected function delayIconRequest(): void
    {
        Sleep::usleep(self::ICON_REQUEST_DELAY_MS * 1000);
    }

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

            $isRetryable = str_contains($message, '429')
                || str_contains($message, '500')
                || str_contains($message, 'timed out')
                || str_contains($message, 'cURL error');

            if ($attempt <= self::MAX_RETRIES && $isRetryable) {
                $delay = self::RATE_LIMIT_WAIT_S * $attempt;
                $this->info(sprintf('Retryable error, waiting %ds (attempt %d/%d)...', $delay, $attempt, self::MAX_RETRIES));
                Sleep::sleep($delay);

                return $this->fetchWithRetry($endpoint, $attempt + 1);
            }

            Log::warning(sprintf('API error [%s]: ', $endpoint).$message);

            return null;
        }
    }

    protected function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }

        Log::info($message);
    }
}
