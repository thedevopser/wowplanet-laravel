<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Models\WowQuest;
use Illuminate\Support\Collection;

class QuestProgressAggregator
{
    /**
     * @param  list<int>  $completedQuestIds
     * @return array<int, array{total: int, completed: int, zones: list<array<string, mixed>>}>
     */
    public function aggregate(array $completedQuestIds, string $faction): array
    {
        $allQuests = $this->loadActiveQuests($faction);

        return $this->buildExpansionProgress($allQuests, $completedQuestIds);
    }

    /**
     * @return Collection<(int|string), Collection<int, WowQuest>>
     */
    private function loadActiveQuests(string $faction): Collection
    {
        return WowQuest::query() // @phpstan-ignore return.type
            ->where('is_active', true)
            ->where(fn (\Illuminate\Contracts\Database\Query\Builder $builder) => $builder->whereNull('faction')->orWhere('faction', $faction))
            ->get()
            ->groupBy('expansion_id');
    }

    /**
     * @param  Collection<(int|string), Collection<int, WowQuest>>  $questsByExpansion
     * @param  list<int>  $completedQuestIds
     * @return array<int, array{total: int, completed: int, zones: list<array<string, mixed>>}>
     */
    private function buildExpansionProgress(Collection $questsByExpansion, array $completedQuestIds): array
    {
        $results = [];

        for ($expansionIndex = 0; $expansionIndex <= 11; $expansionIndex++) {
            /** @var Collection<int, WowQuest> $expansionQuests */
            $expansionQuests = $questsByExpansion->get($expansionIndex, new Collection);
            $zoneProgress = $this->buildZoneProgress($expansionQuests, $completedQuestIds);

            $results[$expansionIndex] = [
                'total' => array_sum(array_column($zoneProgress, 'total')),
                'completed' => array_sum(array_column($zoneProgress, 'completed')),
                'zones' => $zoneProgress,
            ];
        }

        return $results;
    }

    /**
     * @param  Collection<int, WowQuest>  $expansionQuests
     * @param  list<int>  $completedQuestIds
     * @return list<array{name: string, total: int, completed: int, items: list<array<string, mixed>>}>
     */
    private function buildZoneProgress(Collection $expansionQuests, array $completedQuestIds): array
    {
        $zoneProgress = [];
        /** @var Collection<string, Collection<int, WowQuest>> $questsByZone */
        $questsByZone = $expansionQuests->groupBy('zone_name');

        foreach ($questsByZone as $zoneName => $zoneQuests) {
            if (empty($zoneName)) {
                continue;
            }

            $zoneProgress[] = $this->buildSingleZoneProgress((string) $zoneName, $zoneQuests, $completedQuestIds);
        }

        return $zoneProgress;
    }

    /**
     * @param  Collection<int, WowQuest>  $zoneQuests
     * @param  list<int>  $completedQuestIds
     * @return array{name: string, total: int, completed: int, items: list<array<string, mixed>>}
     */
    private function buildSingleZoneProgress(string $zoneName, Collection $zoneQuests, array $completedQuestIds): array
    {
        $items = [];
        $completedCount = 0;

        foreach ($zoneQuests as $zoneQuest) {
            $isCompleted = in_array($zoneQuest->id, $completedQuestIds);
            $items[] = [
                'id' => $zoneQuest->id,
                'name' => $zoneQuest->name_fr,
                'is_completed' => $isCompleted,
            ];
            if ($isCompleted) {
                $completedCount++;
            }
        }

        return [
            'name' => $zoneName,
            'total' => count($items),
            'completed' => $completedCount,
            'items' => $items,
        ];
    }
}
