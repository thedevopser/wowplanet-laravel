<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Models\WowAchievement;
use Illuminate\Support\Collection;

class AchievementProgressAggregator
{
    /**
     * @param  list<int>  $completedAchievementIds
     * @return array<int, array{total: int, completed: int, categories: list<array<string, mixed>>}>
     */
    public function aggregate(array $completedAchievementIds): array
    {
        $allAchievements = WowAchievement::query()
            ->where('is_active', true)
            ->get()
            ->groupBy('expansion_id');

        $results = [];

        for ($expansionIndex = 0; $expansionIndex <= 11; $expansionIndex++) {
            /** @var Collection<int, WowAchievement> $expansionAchievements */
            $expansionAchievements = $allAchievements->get($expansionIndex, new Collection);
            $categoryProgress = $this->buildCategoryProgress($expansionAchievements, $completedAchievementIds);

            $results[$expansionIndex] = [
                'total' => $expansionAchievements->count(),
                'completed' => $expansionAchievements->whereIn('id', $completedAchievementIds)->count(),
                'categories' => $categoryProgress,
            ];
        }

        return $results;
    }

    /**
     * @param  Collection<int, WowAchievement>  $expansionAchievements
     * @param  list<int>  $completedAchievementIds
     * @return list<array{name: string, total: int, completed: int, items: list<array<string, mixed>>}>
     */
    private function buildCategoryProgress(Collection $expansionAchievements, array $completedAchievementIds): array
    {
        $categoryProgress = [];
        /** @var Collection<string, Collection<int, WowAchievement>> $achievementsByCategory */
        $achievementsByCategory = $expansionAchievements->groupBy('category_name');

        foreach ($achievementsByCategory as $categoryName => $categoryAchievements) {
            if (empty($categoryName)) {
                continue;
            }

            $categoryProgress[] = $this->buildSingleCategoryProgress((string) $categoryName, $categoryAchievements, $completedAchievementIds);
        }

        return $categoryProgress;
    }

    /**
     * @param  Collection<int, WowAchievement>  $categoryAchievements
     * @param  list<int>  $completedAchievementIds
     * @return array{name: string, total: int, completed: int, items: list<array<string, mixed>>}
     */
    private function buildSingleCategoryProgress(string $categoryName, Collection $categoryAchievements, array $completedAchievementIds): array
    {
        $items = [];
        $completedCount = 0;

        foreach ($categoryAchievements as $categoryAchievement) {
            $isCompleted = in_array($categoryAchievement->id, $completedAchievementIds);
            $items[] = [
                'id' => $categoryAchievement->id,
                'name' => $categoryAchievement->name_fr,
                'is_completed' => $isCompleted,
            ];
            if ($isCompleted) {
                $completedCount++;
            }
        }

        return [
            'name' => $categoryName,
            'total' => count($items),
            'completed' => $completedCount,
            'items' => $items,
        ];
    }
}
