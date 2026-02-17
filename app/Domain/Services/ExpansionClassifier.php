<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\ValueObjects\ExpansionId;
use App\Infrastructure\Mappings\ExpansionMapping;

class ExpansionClassifier
{
    public function __construct(
        private ExpansionMapping $mapping,
        )
    {
    }

    public function classifyZone(int $zoneId): ExpansionId
    {
        $expansionValue = $this->mapping->getZoneMapping()[$zoneId] ?? ExpansionId::CLASSIC;
        return new ExpansionId($expansionValue);
    }

    public function classifyQuest(int $questId, ?int $zoneId = null): ExpansionId
    {
        if (isset($this->mapping->getQuestMapping()[$questId])) {
            return new ExpansionId($this->mapping->getQuestMapping()[$questId]);
        }

        if ($zoneId !== null) {
            return $this->classifyZone($zoneId);
        }

        return new ExpansionId(ExpansionId::CLASSIC);
    }

    public function classifyAchievement(int $achievementId): ExpansionId
    {
        if (isset($this->mapping->getAchievementMapping()[$achievementId])) {
            return new ExpansionId($this->mapping->getAchievementMapping()[$achievementId]);
        }

        return new ExpansionId(ExpansionId::CLASSIC);
    }

    public function classifyAchievementCategory(int $categoryId): ExpansionId
    {
        $expansionValue = $this->mapping->getAchievementCategoryMapping()[$categoryId] ?? ExpansionId::CLASSIC;
        return new ExpansionId($expansionValue);
    }
}