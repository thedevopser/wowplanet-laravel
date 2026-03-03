<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Services\ExpansionClassifier;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\RateLimitingMiddleware;
use App\Infrastructure\Mappings\ExpansionMapping;
use App\Infrastructure\Mappings\StaticExpansionMapping;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class BlizzardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExpansionMapping::class, StaticExpansionMapping::class);

        $this->app->singleton(
            ExpansionClassifier::class,
            fn (Application $application): ExpansionClassifier => new ExpansionClassifier(
                $application->make(ExpansionMapping::class),
            ),
        );

        $this->app->singleton(
            function (): \App\Infrastructure\Blizzard\BlizzardApiClient {
                $handlerStack = HandlerStack::create();
                $handlerStack->push(new RateLimitingMiddleware, 'rate_limiter');

                /** @var string $region */
                $region = config('services.blizzard.region', 'eu');
                $client = new Client([
                    'base_uri' => sprintf('https://%s.api.blizzard.com/', $region),
                    'handler' => $handlerStack,
                    'timeout' => 5.0,
                ]);

                return new BlizzardApiClient($client);
            },
        );
    }
}
