<?php

declare(strict_types=1);

namespace App\Infrastructure\Mappings;

use App\Domain\ValueObjects\ExpansionId;
use Illuminate\Support\Facades\File;

class StaticExpansionMapping implements ExpansionMapping
{
    private ?array $achievements = null;
    private ?array $quests = null;

    public function getZoneMapping(): array
    {
        return [
            1 => ExpansionId::CLASSIC,
            14 => ExpansionId::CLASSIC,
            3518 => ExpansionId::BURNING_CRUSADE,
            3519 => ExpansionId::BURNING_CRUSADE,
        ];
    }

    public function getQuestMapping(): array
    {
        if ($this->quests === null) {
            $path = storage_path('app/blizzard/mappings/processed/quests.json');
            $this->quests = File::exists($path) ? json_decode(File::get($path), true) : [];
        }

        $mapping = [];
        foreach ($this->quests as $expansionId => $data) {
            foreach ($data['total_ids'] as $id) {
                $mapping[$id] = (int)$expansionId;
            }
        }

        return $mapping;
    }

    public function getAchievementMapping(): array
    {
        if ($this->achievements === null) {
            $path = storage_path('app/blizzard/mappings/processed/achievements.json');
            $this->achievements = File::exists($path) ? json_decode(File::get($path), true) : [];
        }

        $mapping = [];
        foreach ($this->achievements as $expansionId => $data) {
            foreach ($data['total_ids'] as $id) {
                $mapping[$id] = (int)$expansionId;
            }
        }

        return $mapping;
    }

    public function getAchievementCategoryMapping(): array
    {
        return [
            15246 => ExpansionId::DRAGONFLIGHT,
        ];
    }

    /**
     * Get all IDs (Master List) for a specific expansion and type.
     */
    public function getMasterList(int $expansionId, string $type): array
    {
        if ($type === 'achievement') {
            if ($this->achievements === null)
                $this->getAchievementMapping();
            return $this->achievements[$expansionId]['total_ids'] ?? [];
        }

        if ($type === 'quest') {
            if ($this->quests === null)
                $this->getQuestMapping();
            return $this->quests[$expansionId]['total_ids'] ?? [];
        }

        return [];
    }

    public function getQuestsByExpansion(int $expansionId): array
    {
        if ($this->quests === null)
            $this->getQuestMapping();
        return $this->quests[$expansionId] ?? ['total_ids' => [], 'zones' => []];
    }

    public function getAchievementsByExpansion(int $expansionId): array
    {
        if ($this->achievements === null)
            $this->getAchievementMapping();
        return $this->achievements[$expansionId] ?? ['total_ids' => [], 'categories' => []];
    }
}