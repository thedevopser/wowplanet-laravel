<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /** @var string $appUrl */
        $appUrl = config('app.url', '');
        if (str_contains($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->ip()));

        RateLimiter::for('authenticated', fn (Request $request): Limit => Limit::perMinute(30)->by($request->session()->getId()));
    }
}
