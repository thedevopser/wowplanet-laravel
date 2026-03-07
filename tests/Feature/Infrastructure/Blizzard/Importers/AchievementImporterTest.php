<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Importers\AchievementImporter;
use App\Models\WowAchievement;

beforeEach(function (): void {
    setUpBlizzardTempStorage($this);
});

afterEach(function (): void {
    tearDownBlizzardTempStorage($this);
});

test('it imports achievements from SA JSON and CSV', function (): void {
    writeAchievementsJson([
        [
            'name' => 'General',
            'cats' => [
                [
                    'name' => 'Classic',
                    'subcats' => [
                        [
                            'name' => 'Exploration',
                            'items' => [
                                ['id' => 200, 'icon' => 'achievement_test', 'points' => 10],
                                ['id' => 201, 'icon' => 'achievement_other', 'points' => 5],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);
    writeAchievementCsv([
        [200, 'Haut-fait Test'],
        [201, 'Haut-fait Autre'],
    ]);

    $achievementImporter = resolve(AchievementImporter::class);
    $achievementImporter->import();

    expect(WowAchievement::query()->count())->toBe(2);
    expect(WowAchievement::query()->find(200)->name_fr)->toBe('Haut-fait Test');
    expect(WowAchievement::query()->find(200)->category_name)->toBe('General');
    expect(WowAchievement::query()->find(200)->expansion_id)->toBe(0);
    expect(WowAchievement::query()->find(200)->points)->toBe(10);
    expect(WowAchievement::query()->find(200)->icon_url)->toBe('https://wow.zamimg.com/images/wow/icons/medium/achievement_test.jpg');
    expect(WowAchievement::query()->find(200)->is_active)->toBeTrue();
    expect(WowAchievement::query()->find(201)->name_fr)->toBe('Haut-fait Autre');
    expect(WowAchievement::query()->find(201)->points)->toBe(5);
});

test('it returns early when SA JSON is empty', function (): void {
    writeAchievementsJson([]);
    writeAchievementCsv([]);

    $achievementImporter = resolve(AchievementImporter::class);
    $achievementImporter->import();

    expect(WowAchievement::query()->count())->toBe(0);
});

test('it uses fallback expansion_id 0 when SA has null expansion', function (): void {
    writeAchievementsJson([
        [
            'name' => 'General',
            'cats' => [
                [
                    'name' => 'UnknownExpansion',
                    'subcats' => [
                        [
                            'name' => 'SubCategory',
                            'items' => [
                                ['id' => 300, 'icon' => 'achievement_fallback', 'points' => 15],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);
    writeAchievementCsv([
        [300, 'Haut-fait inconnu'],
    ]);

    $achievementImporter = resolve(AchievementImporter::class);
    $achievementImporter->import();

    expect(WowAchievement::query()->count())->toBe(1);
    expect(WowAchievement::query()->find(300)->expansion_id)->toBe(0);
});

// ─── Helpers ────────────────────────────────────────────────

function writeAchievementsJson(array $supercats): void
{
    $json = json_encode(['supercats' => $supercats], JSON_THROW_ON_ERROR);
    file_put_contents(storage_path('app/blizzard/achievements.json'), $json);
}

function writeAchievementCsv(array $rows): void
{
    $lines = ['ID,Title_lang'];
    foreach ($rows as $row) {
        $name = str_replace('"', '""', (string) $row[1]);
        $lines[] = sprintf('"%d","%s"', $row[0], $name);
    }

    file_put_contents(storage_path('app/blizzard/achievement.csv'), implode("\n", $lines));
}
