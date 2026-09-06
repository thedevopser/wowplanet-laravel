<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

// PostgreSQL is transactional, so each test rolls back instead of re-migrating,
// and the lazy variant spares that cost entirely to tests that never touch the
// database.
pest()->extend(TestCase::class)->use(LazilyRefreshDatabase::class)->in('Feature');

/**
 * Set up a temporary blizzard storage directory.
 * Call this inside beforeEach() — it saves the original path and redirects storage_path().
 */
function setUpBlizzardTempStorage(object $test): void
{
    $test->originalStoragePath = app()->storagePath();
    $test->blizzardTmpDir = sys_get_temp_dir().'/pest-blizzard-'.uniqid();
    mkdir($test->blizzardTmpDir.'/app/blizzard', 0755, true);
    app()->useStoragePath($test->blizzardTmpDir);
}

/**
 * Tear down the temporary blizzard storage directory.
 * Call this inside afterEach() — it restores the original path and cleans up.
 */
function tearDownBlizzardTempStorage(object $test): void
{
    app()->useStoragePath($test->originalStoragePath);

    removeDirectory($test->blizzardTmpDir);
}

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($directory);
}

/**
 * Hashes of the favicons tracked in the repository, to prove a test run leaves them alone.
 *
 * @return array<string, string>
 */
function faviconFingerprints(string $publicPath): array
{
    $fingerprints = [];

    // GLOB_BRACE est absent des PHP liés à musl, dont l'image Alpine du projet.
    foreach (['favicon.ico', '*-*x*.png', 'apple-touch-icon.png'] as $pattern) {
        foreach (glob($publicPath.'/'.$pattern) ?: [] as $file) {
            $fingerprints[basename($file)] = (string) md5_file($file);
        }
    }

    return $fingerprints;
}
