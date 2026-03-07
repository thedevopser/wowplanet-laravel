<?php

declare(strict_types=1);

test('it runs without errors', function (): void {
    $logoPath = public_path('images/logo.png');

    if (! file_exists($logoPath)) {
        $this->markTestSkipped('Source logo.png not found — skipping favicon generation test');
    }

    $this->artisan('app:generate-favicons')
        ->assertExitCode(0);
});
