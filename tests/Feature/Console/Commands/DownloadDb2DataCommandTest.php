<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

test('it downloads the remaining faction-mapping DB2 CSV files pinned to the live build', function (): void {
    Http::fake([
        'wago.tools/db2/*/csv*' => Http::response("ID,Name\n1,Test\n", 200),
        'simplearmory.com/data/*' => Http::response('{"test": true}', 200),
    ]);

    $this->artisan('app:download-db2')
        ->assertExitCode(0);

    Http::assertSentCount(9); // 5 DB2 tables (mappings factions/extension) + 4 SimpleArmory JSONs

    // Chaque table DB2 doit être épinglée sur le produit live (jamais le PTR par défaut de wago)
    Http::assertSent(fn ($request): bool => ! str_contains((string) $request->url(), 'wago.tools')
        || str_contains((string) $request->url(), 'product=wow'));
});

test('it downloads a single table with --table', function (): void {
    Http::fake([
        'wago.tools/db2/*/csv*' => Http::response("ID,Name\n1,Test\n", 200),
    ]);

    $this->artisan('app:download-db2', ['--table' => 'Faction'])
        ->assertExitCode(0);

    Http::assertSentCount(1);
});

test('it rejects an unknown table name', function (): void {
    Http::fake();

    $this->artisan('app:download-db2', ['--table' => 'NopeTable'])
        ->assertFailed();

    Http::assertNothingSent();
});

test('it handles download failures gracefully', function (): void {
    Http::fake([
        'wago.tools/db2/*/csv*' => Http::response('Server Error', 500),
        'simplearmory.com/data/*' => Http::response('Not Found', 404),
    ]);

    $this->artisan('app:download-db2')
        ->assertFailed();
});
