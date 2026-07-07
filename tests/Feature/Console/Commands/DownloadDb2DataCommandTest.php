<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

test('it downloads DB2 CSV files', function (): void {
    Http::fake([
        'wago.tools/db2/*/csv*' => Http::response("ID,Name\n1,Test\n", 200),
        'simplearmory.com/data/*' => Http::response('{"test": true}', 200),
    ]);

    $this->artisan('app:download-db2')
        ->assertExitCode(0);

    Http::assertSentCount(24); // 20 DB2 tables + 4 SimpleArmory JSONs
});

test('it handles download failures gracefully', function (): void {
    Http::fake([
        'wago.tools/db2/*/csv*' => Http::response('Server Error', 500),
        'simplearmory.com/data/*' => Http::response('Not Found', 404),
    ]);

    $this->artisan('app:download-db2')
        ->assertFailed();
});
