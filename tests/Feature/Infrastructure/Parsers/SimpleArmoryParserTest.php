<?php

declare(strict_types=1);

use App\Infrastructure\Parsers\SimpleArmoryParser;

beforeEach(function (): void {
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

function saWriteJson(object $testCase, string $filename, mixed $data): void
{
    $path = storage_path('app/blizzard/'.$filename);

    file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
}

function saEnsureMissing(object $testCase, string $filename): void
{
    $path = storage_path('app/blizzard/'.$filename);
    if (file_exists($path)) {
        unlink($path);
    }
}

// --- buildIconUrl ---

test('buildIconUrl returns correct URL for valid icon name', function (): void {
    $url = SimpleArmoryParser::buildIconUrl('ability_mount_drake_blue');

    expect($url)->toBe('https://wow.zamimg.com/images/wow/icons/medium/ability_mount_drake_blue.jpg');
});

test('buildIconUrl lowercases the icon name', function (): void {
    $url = SimpleArmoryParser::buildIconUrl('Achievement_Level_10');

    expect($url)->toBe('https://wow.zamimg.com/images/wow/icons/medium/achievement_level_10.jpg');
});

test('buildIconUrl returns null for empty string', function (): void {
    expect(SimpleArmoryParser::buildIconUrl(''))->toBeNull();
});

test('buildIconUrl accepts numeric FileDataID', function (): void {
    expect(SimpleArmoryParser::buildIconUrl('7425121'))
        ->toBe('https://wow.zamimg.com/images/wow/icons/medium/7425121.jpg');
});

test('buildIconUrl handles apostrophes and ampersands', function (): void {
    expect(SimpleArmoryParser::buildIconUrl("Achievement_Dungeon_Drak'Tharon_Normal"))
        ->toBe("https://wow.zamimg.com/images/wow/icons/medium/achievement_dungeon_drak'tharon_normal.jpg")
        ->and(SimpleArmoryParser::buildIconUrl('inv_misc_fork&knife'))
        ->toBe('https://wow.zamimg.com/images/wow/icons/medium/inv_misc_fork&knife.jpg');
});

// --- parseCollection ---

test('parseCollection returns empty array when file missing', function (): void {
    expect(SimpleArmoryParser::parseCollection('nonexistent.json'))->toBe([]);
});

test('parseCollection parses mounts correctly', function (): void {
    saWriteJson($this, 'mounts.json', [
        [
            'name' => 'The War Within',
            'subcats' => [
                [
                    'name' => 'Achievement',
                    'items' => [
                        ['ID' => 100, 'name' => 'Albino Drake', 'icon' => 'ability_mount_drake_blue', 'spellid' => 60025],
                        ['ID' => 101, 'name' => 'Wolf', 'icon' => 'ability_wolf', 'spellid' => 60026, 'side' => 'H'],
                    ],
                ],
            ],
        ],
    ]);

    $result = SimpleArmoryParser::parseCollection('mounts.json');

    expect($result)->toHaveCount(2)
        ->and($result[100]['category'])->toBe('The War Within')
        ->and($result[100]['source'])->toBe('Achievement')
        ->and($result[100]['icon'])->toBe('ability_mount_drake_blue')
        ->and($result[100]['faction'])->toBeNull()
        ->and($result[100]['spellid'])->toBe(60025)
        ->and($result[101]['faction'])->toBe('Horde');
});

test('parseCollection parses decors with string itemId', function (): void {
    saWriteJson($this, 'decors.json', [
        [
            'name' => 'General',
            'subcats' => [
                [
                    'name' => 'Pawdo Quests',
                    'items' => [
                        ['ID' => 9242, 'name' => 'Crate', 'icon' => '7425121', 'itemId' => '253168'],
                    ],
                ],
            ],
        ],
    ]);

    $result = SimpleArmoryParser::parseCollection('decors.json');

    expect($result)->toHaveCount(1)
        ->and($result[9242]['itemId'])->toBe(253168)
        ->and($result[9242]['icon'])->toBe('7425121');
});

test('parseCollection skips notReleased items', function (): void {
    saWriteJson($this, 'pets.json', [
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Vendor',
                    'items' => [
                        ['ID' => 1, 'name' => 'Cat', 'icon' => 'cat', 'creatureId' => 100, 'spellid' => 200],
                        ['ID' => 2, 'name' => 'Dog', 'icon' => 'dog', 'creatureId' => 101, 'spellid' => 201, 'notReleased' => true],
                    ],
                ],
            ],
        ],
    ]);

    $result = SimpleArmoryParser::parseCollection('pets.json');

    expect($result)->toHaveCount(1)
        ->and($result)->toHaveKey(1)
        ->and($result)->not->toHaveKey(2);
});

test('parseCollection handles empty category name', function (): void {
    saWriteJson($this, 'pets.json', [
        [
            'name' => '',
            'subcats' => [
                [
                    'name' => 'Collect',
                    'items' => [
                        ['ID' => 1, 'name' => 'Cat', 'icon' => 'cat', 'creatureId' => 100, 'spellid' => 200],
                    ],
                ],
            ],
        ],
    ]);

    $result = SimpleArmoryParser::parseCollection('pets.json');

    expect($result)->toHaveCount(1)
        ->and($result[1]['category'])->toBe('');
});

test('parseCollection rejects achievements structure', function (): void {
    saWriteJson($this, 'test.json', ['supercats' => []]);

    expect(SimpleArmoryParser::parseCollection('test.json'))->toBe([]);
});

test('parseCollection preserves creatureId for pets', function (): void {
    saWriteJson($this, 'pets.json', [
        [
            'name' => 'Classic',
            'subcats' => [
                [
                    'name' => 'Drop',
                    'items' => [
                        ['ID' => 160, 'name' => 'Stinker', 'icon' => 'inv_pet', 'creatureId' => 23274, 'spellid' => 40990, 'itemId' => 40653],
                    ],
                ],
            ],
        ],
    ]);

    $result = SimpleArmoryParser::parseCollection('pets.json');

    expect($result[160]['creatureId'])->toBe(23274)
        ->and($result[160]['spellid'])->toBe(40990)
        ->and($result[160]['itemId'])->toBe(40653);
});
