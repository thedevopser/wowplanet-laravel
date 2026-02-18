<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Services;

use App\Domain\Services\ExpansionClassifier;
use App\Domain\ValueObjects\ExpansionId;
use App\Infrastructure\Mappings\ExpansionMapping;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExpansionClassifierTest extends TestCase
{
    private ExpansionClassifier $expansionClassifier;

    protected function setUp(): void
    {
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
    }

    #[Test]
    public function classifyZoneReturnsCorrectExpansionForKnownZone(): void
    {
        $expansionId = $this->expansionClassifier->classifyZone(3518);

        $this->assertSame(ExpansionId::BURNING_CRUSADE, $expansionId->value);
    }

    #[Test]
    public function classifyZoneFallsBackToClassicForUnknownZone(): void
    {
        $expansionId = $this->expansionClassifier->classifyZone(99999);

        $this->assertSame(ExpansionId::CLASSIC, $expansionId->value);
    }

    #[Test]
    public function classifyQuestReturnsCorrectExpansionForKnownQuest(): void
    {
        $expansionId = $this->expansionClassifier->classifyQuest(50000);

        $this->assertSame(ExpansionId::LEGION, $expansionId->value);
    }

    #[Test]
    public function classifyQuestDelegatesToZoneWhenQuestUnknownAndZoneProvided(): void
    {
        $expansionId = $this->expansionClassifier->classifyQuest(99999, 7502);

        $this->assertSame(ExpansionId::THE_WAR_WITHIN, $expansionId->value);
    }

    #[Test]
    public function classifyQuestFallsBackToClassicWhenQuestUnknownAndNoZone(): void
    {
        $expansionId = $this->expansionClassifier->classifyQuest(99999);

        $this->assertSame(ExpansionId::CLASSIC, $expansionId->value);
    }

    #[Test]
    public function classifyQuestPrioritizesQuestMappingOverZone(): void
    {
        $expansionId = $this->expansionClassifier->classifyQuest(80000, 1);

        $this->assertSame(ExpansionId::THE_WAR_WITHIN, $expansionId->value);
    }

    #[Test]
    public function classifyAchievementReturnsCorrectExpansionForKnownAchievement(): void
    {
        $expansionId = $this->expansionClassifier->classifyAchievement(5000);

        $this->assertSame(ExpansionId::DRAGONFLIGHT, $expansionId->value);
    }

    #[Test]
    public function classifyAchievementFallsBackToClassicForUnknownAchievement(): void
    {
        $expansionId = $this->expansionClassifier->classifyAchievement(99999);

        $this->assertSame(ExpansionId::CLASSIC, $expansionId->value);
    }

    #[Test]
    public function classifyAchievementCategoryReturnsCorrectExpansion(): void
    {
        $expansionId = $this->expansionClassifier->classifyAchievementCategory(15246);

        $this->assertSame(ExpansionId::DRAGONFLIGHT, $expansionId->value);
    }

    #[Test]
    public function classifyAchievementCategoryFallsBackToClassic(): void
    {
        $expansionId = $this->expansionClassifier->classifyAchievementCategory(99999);

        $this->assertSame(ExpansionId::CLASSIC, $expansionId->value);
    }
}
