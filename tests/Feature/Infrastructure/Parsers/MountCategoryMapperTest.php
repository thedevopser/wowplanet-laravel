<?php

declare(strict_types=1);

use App\Infrastructure\Parsers\MountCategoryMapper;

beforeEach(function (): void {
    $dir = storage_path('app/blizzard');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $path = $dir.'/mounts_categories.json';
    if (file_exists($path)) {
        $backup = $path.'.testbak';
        rename($path, $backup);
        $this->backup = $backup;
    }

    $this->jsonPath = $path;
});

afterEach(function (): void {
    if (file_exists($this->jsonPath)) {
        unlink($this->jsonPath);
    }

    if (property_exists($this, 'backup') && $this->backup !== null && file_exists($this->backup)) {
        rename($this->backup, $this->jsonPath);
    }
});

test('build returns empty array when file does not exist', function (): void {
    expect(MountCategoryMapper::build())->toBe([]);
});

test('build parses JSON and maps mount IDs to category and source', function (): void {
    file_put_contents($this->jsonPath, json_encode([
        [
            'name' => 'The War Within',
            'subcats' => [
                [
                    'name' => 'Achievement',
                    'items' => [
                        ['ID' => 100, 'name' => 'Drake doré', 'icon' => '123', 'spellid' => 50001],
                        ['ID' => 101, 'name' => 'Loup blindé', 'icon' => '124', 'spellid' => 50002],
                    ],
                ],
                [
                    'name' => 'Vendor',
                    'items' => [
                        ['ID' => 200, 'name' => 'Destrier', 'icon' => '125', 'spellid' => 50003],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Racial',
            'subcats' => [
                [
                    'name' => 'Human',
                    'items' => [
                        ['ID' => 300, 'name' => 'Cheval brun', 'icon' => '126', 'spellid' => 50004],
                    ],
                ],
            ],
        ],
    ]));

    $map = MountCategoryMapper::build();

    expect($map)->toHaveCount(4)
        ->and($map[100])->toBe(['category' => 'The War Within', 'source' => 'Achievement'])
        ->and($map[101])->toBe(['category' => 'The War Within', 'source' => 'Achievement'])
        ->and($map[200])->toBe(['category' => 'The War Within', 'source' => 'Vendor'])
        ->and($map[300])->toBe(['category' => 'Racial', 'source' => 'Human']);
});

test('build skips items with empty category or source names', function (): void {
    file_put_contents($this->jsonPath, json_encode([
        [
            'name' => '',
            'subcats' => [
                ['name' => 'Quest', 'items' => [['ID' => 1]]],
            ],
        ],
        [
            'name' => 'Valid',
            'subcats' => [
                ['name' => '', 'items' => [['ID' => 2]]],
            ],
        ],
    ]));

    $map = MountCategoryMapper::build();

    expect($map)->toBe([]);
});
