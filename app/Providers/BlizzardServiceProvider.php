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
use Illuminate\Support\ServiceProvider;

class BlizzardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExpansionMapping::class , StaticExpansionMapping::class);

        $this->app->singleton(ExpansionClassifier::class , function ($app) {
            return new ExpansionClassifier($app->make(ExpansionMapping::class));
        });

        $this->app->singleton(BlizzardApiClient::class , function ($app) {
            $stack = HandlerStack::create();
            // In a real scenario, we'd add the RateLimitingMiddleware here
            // $stack->push(new RateLimitingMiddleware());

            $region = config('services.blizzard.region', 'eu');
            $client = new Client([
                'base_uri' => "https://{$region}.api.blizzard.com/",
                'handler' => $stack,
                'timeout' => 5.0,
            ]);

            return new BlizzardApiClient($client);
        });
    }
}