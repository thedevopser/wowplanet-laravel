<?php

declare(strict_types=1);

use App\Infrastructure\Parsers\DecorCategoryMapper;

beforeEach(function (): void {
    $dir = storage_path('app/blizzard');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $path = $dir.'/decors_categories.json';
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
    expect(DecorCategoryMapper::build())->toBe([]);
});

test('build parses JSON and maps decor IDs to category and source', function (): void {
    file_put_contents($this->jsonPath, json_encode([
        [
            'name' => 'The War Within',
            'subcats' => [
                [
                    'name' => 'Quest',
                    'items' => [
                        ['ID' => 100, 'name' => 'Statue dorée', 'icon' => '123', 'itemId' => '456'],
                        ['ID' => 101, 'name' => 'Torche murale', 'icon' => '124', 'itemId' => '457'],
                    ],
                ],
                [
                    'name' => 'Achievement',
                    'items' => [
                        ['ID' => 200, 'name' => 'Trophée', 'icon' => '125', 'itemId' => '458'],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Midnight',
            'subcats' => [
                [
                    'name' => 'Vendor',
                    'items' => [
                        ['ID' => 300, 'name' => 'Tapis luxueux', 'icon' => '126', 'itemId' => '459'],
                    ],
                ],
            ],
        ],
    ]));

    $map = DecorCategoryMapper::build();

    expect($map)->toHaveCount(4)
        ->and($map[100])->toBe(['category' => 'The War Within', 'source' => 'Quest'])
        ->and($map[101])->toBe(['category' => 'The War Within', 'source' => 'Quest'])
        ->and($map[200])->toBe(['category' => 'The War Within', 'source' => 'Achievement'])
        ->and($map[300])->toBe(['category' => 'Midnight', 'source' => 'Vendor']);
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

    $map = DecorCategoryMapper::build();

    expect($map)->toBe([]);
});
