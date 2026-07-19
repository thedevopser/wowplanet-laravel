<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    \Illuminate\Support\Facades\Date::setTestNow('2026-07-19 10:00:00');
});

afterEach(function (): void {
    \Illuminate\Support\Facades\Date::setTestNow();
});

test('it allows requests when under the hourly limit', function (): void {
    $guard = new HourlyBudgetGuard;

    expect($guard->secondsUntilAvailable(1000))->toBe(0);
});

test('it tracks consumed requests and blocks when the limit would be exceeded', function (): void {
    $guard = new HourlyBudgetGuard;

    $guard->consume(HourlyBudgetGuard::HOURLY_LIMIT);

    expect($guard->secondsUntilAvailable(1))->toBeGreaterThan(0);
});

test('it stays available while cumulative usage remains within the limit', function (): void {
    $guard = new HourlyBudgetGuard;

    $guard->consume(HourlyBudgetGuard::HOURLY_LIMIT - 500);

    expect($guard->secondsUntilAvailable(500))->toBe(0)
        ->and($guard->secondsUntilAvailable(501))->toBeGreaterThan(0);
});

test('it forgets usage older than one hour', function (): void {
    $guard = new HourlyBudgetGuard;

    $guard->consume(HourlyBudgetGuard::HOURLY_LIMIT);

    expect($guard->secondsUntilAvailable(1))->toBeGreaterThan(0);

    \Illuminate\Support\Facades\Date::setTestNow('2026-07-19 11:01:00');

    expect($guard->secondsUntilAvailable(1))->toBe(0);
});

test('it computes the wait as the delay until the oldest bucket leaves the window', function (): void {
    $guard = new HourlyBudgetGuard;

    // Tout le budget consommé à 10:00 → il faut attendre ~1h (3600s) pour libérer le bucket
    $guard->consume(HourlyBudgetGuard::HOURLY_LIMIT);

    $wait = $guard->secondsUntilAvailable(1);

    expect($wait)->toBeGreaterThan(3500)->toBeLessThanOrEqual(3660);
});
