<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\ValueObjects\ExpansionId;
use App\Infrastructure\Mappings\ExpansionMapping;

class ExpansionClassifier
{
    public function __construct(
        private readonly ExpansionMapping $expansionMapping,
    ) {
    }

    public function classifyZone(int $zoneId): ExpansionId
    {
        $expansionValue = $this->expansionMapping->getZoneMapping()[$zoneId] ?? ExpansionId::CLASSIC;
        return new ExpansionId($expansionValue);
    }

    public function classifyQuest(int $questId, ?int $zoneId = null): ExpansionId
    {
        if (isset($this->expansionMapping->getQuestMapping()[$questId])) {
            return new ExpansionId($this->expansionMapping->getQuestMapping()[$questId]);
        }

        if ($zoneId !== null) {
            return $this->classifyZone($zoneId);
        }

        return new ExpansionId(ExpansionId::CLASSIC);
    }

    public function classifyAchievement(int $achievementId): ExpansionId
    {
        if (isset($this->expansionMapping->getAchievementMapping()[$achievementId])) {
            return new ExpansionId($this->expansionMapping->getAchievementMapping()[$achievementId]);
        }

        return new ExpansionId(ExpansionId::CLASSIC);
    }

    public function classifyAchievementCategory(int $categoryId): ExpansionId
    {
        $expansionValue = $this->expansionMapping->getAchievementCategoryMapping()[$categoryId] ?? ExpansionId::CLASSIC;
        return new ExpansionId($expansionValue);
    }
}
