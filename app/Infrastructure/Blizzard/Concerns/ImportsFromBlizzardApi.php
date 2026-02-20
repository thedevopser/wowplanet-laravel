<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Concerns;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

trait ImportsFromBlizzardApi
{
    private const REQUEST_DELAY_MS = 150;

    private const ICON_REQUEST_DELAY_MS = 300;

    private const RATE_LIMIT_WAIT_S = 10;

    private const MAX_RETRIES = 3;

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
            if ($attempt < self::MAX_RETRIES && str_contains($exception->getMessage(), '429')) {
                $delay = self::RATE_LIMIT_WAIT_S * $attempt;
                $this->info(sprintf('Rate limit hit, waiting %ds (attempt %d/%d)...', $delay, $attempt, self::MAX_RETRIES));
                Sleep::sleep($delay);

                return $this->fetchWithRetry($endpoint, $attempt + 1);
            }

            if (str_contains($exception->getMessage(), '404')) {
                return null;
            }

            Log::warning(sprintf('API error [%s]: ', $endpoint).$exception->getMessage());

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
