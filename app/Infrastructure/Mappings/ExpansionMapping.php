<?php

declare(strict_types=1);

namespace App\Infrastructure\Mappings;

interface ExpansionMapping
{
    /**
     * @return array<int, int> Map of ID to ExpansionId value
     */
    public function getZoneMapping(): array;

    /**
     * @return array<int, int> Map of Quest ID to ExpansionId value
     */
    public function getQuestMapping(): array;

    /**
     * @return array<int, int> Map of Category ID to ExpansionId value
     */
    public function getAchievementCategoryMapping(): array;

    /**
     * @return array<int, int> Map of Achievement ID to ExpansionId value
     */
    public function getAchievementMapping(): array;

    /**
     * Get all IDs (Master List) for a specific expansion and type.
     *
     * @return list<int>
     */
    public function getMasterList(int $expansionId, string $type): array;

    /**
     * Get quests grouped by zone for an expansion.
     *
     * @return array{total_ids: list<int>, zones?: array<string, mixed>}
     */
    public function getQuestsByExpansion(int $expansionId): array;

    /**
     * Get achievements grouped by category for an expansion.
     *
     * @return array{total_ids: list<int>, categories?: array<string, mixed>}
     */
    public function getAchievementsByExpansion(int $expansionId): array;
}
