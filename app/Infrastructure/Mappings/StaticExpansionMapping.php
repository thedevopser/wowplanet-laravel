<?php

declare(strict_types=1);

namespace App\Infrastructure\Mappings;

use App\Domain\ValueObjects\ExpansionId;
use Illuminate\Support\Facades\File;

class StaticExpansionMapping implements ExpansionMapping
{
    /** @var array<int|string, array{total_ids: list<int>, categories?: array<string, mixed>}>|null */
    private ?array $achievements = null;

    /** @var array<int|string, array{total_ids: list<int>, zones?: array<string, mixed>}>|null */
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

    /**
     * @return array<int, int>
     */
    public function getQuestMapping(): array
    {
        if ($this->quests === null) {
            $path = storage_path('app/blizzard/mappings/processed/quests.json');
            /** @var array<int|string, array{total_ids: list<int>, zones?: array<string, mixed>}> $decoded */
            $decoded = File::exists($path) ? json_decode(File::get($path), true) : [];
            $this->quests = $decoded;
        }

        $mapping = [];
        foreach ($this->quests as $expansionId => $data) {
            foreach ($data['total_ids'] as $id) {
                $mapping[$id] = (int) $expansionId;
            }
        }

        return $mapping;
    }

    /**
     * @return array<int, int>
     */
    public function getAchievementMapping(): array
    {
        if ($this->achievements === null) {
            $path = storage_path('app/blizzard/mappings/processed/achievements.json');
            /** @var array<int|string, array{total_ids: list<int>, categories?: array<string, mixed>}> $decoded */
            $decoded = File::exists($path) ? json_decode(File::get($path), true) : [];
            $this->achievements = $decoded;
        }

        $mapping = [];
        foreach ($this->achievements as $expansionId => $data) {
            foreach ($data['total_ids'] as $id) {
                $mapping[$id] = (int) $expansionId;
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
    /**
     * @return list<int>
     */
    public function getMasterList(int $expansionId, string $type): array
    {
        if ($type === 'achievement') {
            if ($this->achievements === null) {
                $this->getAchievementMapping();
            }

            return $this->achievements[$expansionId]['total_ids'] ?? [];
        }

        if ($type === 'quest') {
            if ($this->quests === null) {
                $this->getQuestMapping();
            }

            return $this->quests[$expansionId]['total_ids'] ?? [];
        }

        return [];
    }

    /**
     * @return array{total_ids: list<int>, zones?: array<string, mixed>}
     */
    public function getQuestsByExpansion(int $expansionId): array
    {
        if ($this->quests === null) {
            $this->getQuestMapping();
        }

        return $this->quests[$expansionId] ?? ['total_ids' => [], 'zones' => []];
    }

    /**
     * @return array{total_ids: list<int>, categories?: array<string, mixed>}
     */
    public function getAchievementsByExpansion(int $expansionId): array
    {
        if ($this->achievements === null) {
            $this->getAchievementMapping();
        }

        return $this->achievements[$expansionId] ?? ['total_ids' => [], 'categories' => []];
    }
}
