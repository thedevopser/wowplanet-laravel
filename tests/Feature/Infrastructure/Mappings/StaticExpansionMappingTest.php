<?php

declare(strict_types=1);

use App\Domain\ValueObjects\ExpansionId;
use App\Infrastructure\Mappings\StaticExpansionMapping;

beforeEach(function (): void {
    $this->mapping = new StaticExpansionMapping;

    $dir = storage_path('app/blizzard/mappings/processed');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
});

afterEach(function (): void {
    foreach (['quests.json', 'achievements.json'] as $file) {
        $path = storage_path('app/blizzard/mappings/processed/'.$file);
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

// ─── getZoneMapping ─────────────────────────────────────────

test('getZoneMapping returns static continent to expansion mapping', function (): void {
    $map = $this->mapping->getZoneMapping();

    expect($map)->toBeArray();
    expect($map[1])->toBe(ExpansionId::CLASSIC);
    expect($map[3518])->toBe(ExpansionId::BURNING_CRUSADE);
});

// ─── getQuestMapping ────────────────────────────────────────

test('getQuestMapping builds map from quests.json', function (): void {
    file_put_contents(
        storage_path('app/blizzard/mappings/processed/quests.json'),
        json_encode([
            '0' => ['total_ids' => [100, 101], 'zones' => []],
            '10' => ['total_ids' => [500], 'zones' => []],
        ])
    );

    $map = $this->mapping->getQuestMapping();

    expect($map[100])->toBe(0);
    expect($map[101])->toBe(0);
    expect($map[500])->toBe(10);
});

test('getQuestMapping returns empty when file missing', function (): void {
    expect($this->mapping->getQuestMapping())->toBe([]);
});

// ─── getAchievementMapping ──────────────────────────────────

test('getAchievementMapping builds map from achievements.json', function (): void {
    file_put_contents(
        storage_path('app/blizzard/mappings/processed/achievements.json'),
        json_encode([
            '0' => ['total_ids' => [10, 20]],
            '7' => ['total_ids' => [300]],
        ])
    );

    $map = $this->mapping->getAchievementMapping();

    expect($map[10])->toBe(0);
    expect($map[300])->toBe(7);
});

test('getAchievementMapping returns empty when file missing', function (): void {
    expect($this->mapping->getAchievementMapping())->toBe([]);
});

// ─── getAchievementCategoryMapping ──────────────────────────

test('getAchievementCategoryMapping returns Dragonflight override', function (): void {
    $map = $this->mapping->getAchievementCategoryMapping();

    expect($map[15246])->toBe(ExpansionId::DRAGONFLIGHT);
});

// ─── getMasterList ──────────────────────────────────────────

test('getMasterList returns quest IDs for expansion', function (): void {
    file_put_contents(
        storage_path('app/blizzard/mappings/processed/quests.json'),
        json_encode(['0' => ['total_ids' => [1, 2, 3], 'zones' => []]])
    );

    $ids = $this->mapping->getMasterList(0, 'quest');

    expect($ids)->toBe([1, 2, 3]);
});

test('getMasterList returns achievement IDs for expansion', function (): void {
    file_put_contents(
        storage_path('app/blizzard/mappings/processed/achievements.json'),
        json_encode(['7' => ['total_ids' => [100, 200]]])
    );

    $ids = $this->mapping->getMasterList(7, 'achievement');

    expect($ids)->toBe([100, 200]);
});

test('getMasterList returns empty for unknown type', function (): void {
    expect($this->mapping->getMasterList(0, 'unknown'))->toBe([]);
});

// ─── getQuestsByExpansion / getAchievementsByExpansion ───────

test('getQuestsByExpansion returns data for existing expansion', function (): void {
    file_put_contents(
        storage_path('app/blizzard/mappings/processed/quests.json'),
        json_encode(['0' => ['total_ids' => [1], 'zones' => ['Durotar' => ['ids' => [1]]]]])
    );

    $data = $this->mapping->getQuestsByExpansion(0);

    expect($data['total_ids'])->toBe([1]);
    expect($data['zones'])->toHaveKey('Durotar');
});

test('getAchievementsByExpansion returns default for unknown expansion', function (): void {
    $data = $this->mapping->getAchievementsByExpansion(99);

    expect($data['total_ids'])->toBe([]);
    expect($data['categories'])->toBe([]);
});
