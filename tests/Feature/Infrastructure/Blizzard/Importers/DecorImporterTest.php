<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Importers\DecorImporter;
use App\Models\WowDecor;

beforeEach(function (): void {
    $dir = storage_path('app/blizzard');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $this->testFiles = ['decors.json', 'housetdecor.csv'];
    $this->backups = [];
    foreach ($this->testFiles as $file) {
        $path = $dir.'/'.$file;
        if (file_exists($path)) {
            $backup = $path.'.testbak';
            rename($path, $backup);
            $this->backups[$path] = $backup;
        }
    }
});

afterEach(function (): void {
    foreach ($this->testFiles as $file) {
        $path = storage_path('app/blizzard/'.$file);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    foreach ($this->backups as $path => $backup) {
        if (file_exists($backup)) {
            rename($backup, $path);
        }
    }
});

test('it imports decors from SA JSON and CSV', function (): void {
    writeDecorsJson([
        [
            'name' => 'The War Within',
            'subcats' => [
                [
                    'name' => 'Quest',
                    'items' => [
                        ['ID' => 400, 'name' => 'TestDecor', 'icon' => 'decor_test', 'spellid' => 0, 'creatureId' => 0, 'itemId' => '555', 'faction' => null, 'quality' => 1, 'notObtainable' => false],
                        ['ID' => 401, 'name' => 'OtherDecor', 'icon' => 'decor_other', 'spellid' => 0, 'creatureId' => 0, 'itemId' => '556', 'faction' => null, 'quality' => 1, 'notObtainable' => false],
                    ],
                ],
            ],
        ],
    ]);
    writeDecorCsv([
        [400, 'Decoration Test'],
        [401, 'Tapis elfique'],
    ]);

    $decorImporter = resolve(DecorImporter::class);
    $decorImporter->import();

    expect(WowDecor::query()->count())->toBe(2);
    expect(WowDecor::query()->find(400)->name_fr)->toBe('Decoration Test');
    expect(WowDecor::query()->find(400)->category)->toBe('The War Within');
    expect(WowDecor::query()->find(400)->source)->toBe('Quest');
    expect(WowDecor::query()->find(400)->item_id)->toBe(555);
    expect(WowDecor::query()->find(400)->is_active)->toBeTrue();
    expect(WowDecor::query()->find(401)->name_fr)->toBe('Tapis elfique');
});

test('it marks not obtainable decors as inactive', function (): void {
    writeDecorsJson([
        [
            'name' => 'Undiscovered',
            'subcats' => [
                [
                    'name' => 'Undiscovered Sources',
                    'items' => [
                        ['ID' => 500, 'name' => 'HiddenDecor', 'icon' => 'decor_hidden', 'spellid' => 0, 'creatureId' => 0, 'itemId' => '700', 'faction' => null, 'quality' => 1, 'notObtainable' => true],
                    ],
                ],
            ],
        ],
        [
            'name' => 'The War Within',
            'subcats' => [
                [
                    'name' => 'Quest',
                    'items' => [
                        ['ID' => 501, 'name' => 'VisibleDecor', 'icon' => 'decor_visible', 'spellid' => 0, 'creatureId' => 0, 'itemId' => '701', 'faction' => null, 'quality' => 1, 'notObtainable' => false],
                    ],
                ],
            ],
        ],
    ]);
    writeDecorCsv([
        [500, 'Decor cache'],
        [501, 'Foyer orne'],
    ]);

    $decorImporter = resolve(DecorImporter::class);
    $decorImporter->import();

    expect(WowDecor::query()->count())->toBe(2);
    expect(WowDecor::query()->find(500)->is_active)->toBeFalse();
    expect(WowDecor::query()->find(500)->category)->toBe('Undiscovered');
    expect(WowDecor::query()->find(501)->is_active)->toBeTrue();
});

test('it returns early when SA JSON is empty', function (): void {
    writeDecorsJson([]);
    writeDecorCsv([]);

    $decorImporter = resolve(DecorImporter::class);
    $decorImporter->import();

    expect(WowDecor::query()->count())->toBe(0);
});

// ─── Helpers ────────────────────────────────────────────────

function writeDecorsJson(array $categories): void
{
    $json = json_encode($categories, JSON_THROW_ON_ERROR);
    file_put_contents(storage_path('app/blizzard/decors.json'), $json);
}

function writeDecorCsv(array $rows): void
{
    $lines = ['ID,Name_lang'];
    foreach ($rows as $row) {
        $name = str_replace('"', '""', (string) $row[1]);
        $lines[] = sprintf('"%d","%s"', $row[0], $name);
    }

    file_put_contents(storage_path('app/blizzard/housetdecor.csv'), implode("\n", $lines));
}
