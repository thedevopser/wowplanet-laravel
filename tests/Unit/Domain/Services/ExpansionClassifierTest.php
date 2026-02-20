<?php

declare(strict_types=1);

use App\Domain\Services\ExpansionClassifier;
use App\Domain\ValueObjects\ExpansionId;
use App\Infrastructure\Mappings\ExpansionMapping;

beforeEach(function (): void {
    $mapping = $this->createMock(ExpansionMapping::class);

    $mapping->method('getZoneMapping')->willReturn([
        1 => ExpansionId::CLASSIC,
        3518 => ExpansionId::BURNING_CRUSADE,
        7502 => ExpansionId::THE_WAR_WITHIN,
    ]);

    $mapping->method('getQuestMapping')->willReturn([
        1000 => ExpansionId::CLASSIC,
        50000 => ExpansionId::LEGION,
        80000 => ExpansionId::THE_WAR_WITHIN,
    ]);

    $mapping->method('getAchievementMapping')->willReturn([
        100 => ExpansionId::WRATH_OF_THE_LICH_KING,
        5000 => ExpansionId::DRAGONFLIGHT,
    ]);

    $mapping->method('getAchievementCategoryMapping')->willReturn([
        15246 => ExpansionId::DRAGONFLIGHT,
        200 => ExpansionId::CATACLYSM,
    ]);

    $this->expansionClassifier = new ExpansionClassifier($mapping);
});

test('classify zone returns correct expansion for known zone', function (): void {
    $expansionId = $this->expansionClassifier->classifyZone(3518);

    expect($expansionId->value)->toBe(ExpansionId::BURNING_CRUSADE);
});

test('classify zone falls back to classic for unknown zone', function (): void {
    $expansionId = $this->expansionClassifier->classifyZone(99999);

    expect($expansionId->value)->toBe(ExpansionId::CLASSIC);
});

test('classify quest returns correct expansion for known quest', function (): void {
    $expansionId = $this->expansionClassifier->classifyQuest(50000);

    expect($expansionId->value)->toBe(ExpansionId::LEGION);
});

test('classify quest delegates to zone when quest unknown and zone provided', function (): void {
    $expansionId = $this->expansionClassifier->classifyQuest(99999, 7502);

    expect($expansionId->value)->toBe(ExpansionId::THE_WAR_WITHIN);
});

test('classify quest falls back to classic when quest unknown and no zone', function (): void {
    $expansionId = $this->expansionClassifier->classifyQuest(99999);

    expect($expansionId->value)->toBe(ExpansionId::CLASSIC);
});

test('classify quest prioritizes quest mapping over zone', function (): void {
    $expansionId = $this->expansionClassifier->classifyQuest(80000, 1);

    expect($expansionId->value)->toBe(ExpansionId::THE_WAR_WITHIN);
});

test('classify achievement returns correct expansion for known achievement', function (): void {
    $expansionId = $this->expansionClassifier->classifyAchievement(5000);

    expect($expansionId->value)->toBe(ExpansionId::DRAGONFLIGHT);
});

test('classify achievement falls back to classic for unknown achievement', function (): void {
    $expansionId = $this->expansionClassifier->classifyAchievement(99999);

    expect($expansionId->value)->toBe(ExpansionId::CLASSIC);
});

test('classify achievement category returns correct expansion', function (): void {
    $expansionId = $this->expansionClassifier->classifyAchievementCategory(15246);

    expect($expansionId->value)->toBe(ExpansionId::DRAGONFLIGHT);
});

test('classify achievement category falls back to classic', function (): void {
    $expansionId = $this->expansionClassifier->classifyAchievementCategory(99999);

    expect($expansionId->value)->toBe(ExpansionId::CLASSIC);
});
