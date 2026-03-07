<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Importers\MountImporter;
use App\Models\WowMount;

beforeEach(function (): void {
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

test('it imports mounts from SA JSON and CSV data', function (): void {
    writeMountsJson([
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Reputation',
                    'items' => [
                        ['ID' => 100, 'name' => 'TestMount1', 'icon' => 'ability_mount_test', 'spellid' => 1234, 'creatureId' => 5678, 'itemId' => null, 'faction' => 'Alliance', 'quality' => 4],
                        ['ID' => 101, 'name' => 'TestMount2', 'icon' => 'ability_mount_horse', 'spellid' => 0, 'creatureId' => 0, 'itemId' => null, 'faction' => null, 'quality' => 3],
                    ],
                ],
            ],
        ],
    ]);
    writeMountCsv([
        [100, 'Monture Test'],
        [101, 'Destrier squelette'],
    ]);

    $mountImporter = resolve(MountImporter::class);
    $mountImporter->import();

    expect(WowMount::query()->count())->toBe(2);
    expect(WowMount::query()->find(100)->name_fr)->toBe('Monture Test');
    expect(WowMount::query()->find(100)->category)->toBe('Classic');
    expect(WowMount::query()->find(100)->source)->toBe('Reputation');
    expect(WowMount::query()->find(100)->icon_url)->toBe('https://wow.zamimg.com/images/wow/icons/medium/ability_mount_test.jpg');
    expect(WowMount::query()->find(100)->source_spell_id)->toBe(1234);
    expect(WowMount::query()->find(100)->is_active)->toBeTrue();
    expect(WowMount::query()->find(101)->name_fr)->toBe('Destrier squelette');
});

test('it returns early when SA JSON is empty', function (): void {
    writeMountsJson([]);
    writeMountCsv([]);

    $mountImporter = resolve(MountImporter::class);
    $mountImporter->import();

    expect(WowMount::query()->count())->toBe(0);
});

test('it uses fallback name when CSV has no French name', function (): void {
    writeMountsJson([
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Drop',
                    'items' => [
                        ['ID' => 200, 'name' => 'EnglishMount', 'icon' => 'ability_mount_en', 'spellid' => 0, 'creatureId' => 0, 'itemId' => null, 'faction' => null, 'quality' => 3],
                    ],
                ],
            ],
        ],
    ]);
    writeMountCsv([]);

    $mountImporter = resolve(MountImporter::class);
    $mountImporter->import();

    expect(WowMount::query()->count())->toBe(1);
    expect(WowMount::query()->find(200)->name_fr)->toStartWith('[EN]');
});

// ─── Helpers ────────────────────────────────────────────────

function writeMountsJson(array $categories): void
{
    $json = json_encode($categories, JSON_THROW_ON_ERROR);
    file_put_contents(storage_path('app/blizzard/mounts.json'), $json);
}

function writeMountCsv(array $rows): void
{
    $lines = ['ID,Name_lang'];
    foreach ($rows as $row) {
        $name = str_replace('"', '""', (string) $row[1]);
        $lines[] = sprintf('"%d","%s"', $row[0], $name);
    }

    file_put_contents(storage_path('app/blizzard/mount.csv'), implode("\n", $lines));
}
