<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\ExpansionTierMatcher;
use App\Models\WowAchievement;

class AchievementImporter
{
    use ImportsFromBlizzardApi;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
    ) {}

    /**
     * @param  array<int, int>  $addonExpansionMap  [achievement_id => expansion_id]
     */
    public function import(array $addonExpansionMap = []): void
    {
        $this->info('Fetching achievement category tree...');

        $index = $this->fetchWithRetry('data/wow/achievement-category/index');
        if (! $index) {
            $this->info('ERROR: Could not fetch achievement category index.');

            return;
        }

        /** @var list<array{id: int, name: string}> $rootCategories */
        $rootCategories = $index['root_categories'] ?? [];
        $this->info('Found '.count($rootCategories).' root categories.');
        $this->info('Addon expansion map: '.count($addonExpansionMap).' achievement IDs.');

        $achievements = [];
        foreach ($rootCategories as $rootCategory) {
            $this->info('  Traversing: '.$rootCategory['name']);
            $this->traverseCategory($rootCategory['id'], $rootCategory['name'], null, $achievements);
        }

        $this->saveAchievements($achievements, $addonExpansionMap);
    }

    /**
     * @param  list<array{id: int, name_fr: string, expansion_id: int, category_name: string}>  $achievements
     * @param  array<int, int>  $addonExpansionMap
     */
    private function saveAchievements(array $achievements, array $addonExpansionMap): void
    {
        $this->info('Saving '.count($achievements).' achievements to database...');
        $mapped = 0;
        $unmapped = 0;
        $count = 0;

        foreach ($achievements as $achievement) {
            $expansionId = $addonExpansionMap[$achievement['id']] ?? $achievement['expansion_id'];
            $mapped += isset($addonExpansionMap[$achievement['id']]) ? 1 : 0;
            $unmapped += isset($addonExpansionMap[$achievement['id']]) ? 0 : 1;

            WowAchievement::query()->updateOrCreate(['id' => $achievement['id']], [
                'name_fr' => $achievement['name_fr'],
                'expansion_id' => $expansionId,
                'category_name' => $achievement['category_name'],
                'is_active' => true,
            ]);
            $count++;
            if ($count % 500 === 0) {
                $this->info(sprintf('  Saved %d...', $count));
            }
        }

        $this->info(sprintf('Achievement import complete: %d total (%d mapped via addon, %d from category tree).', $count, $mapped, $unmapped));
    }

    /**
     * @param  list<array{id: int, name_fr: string, expansion_id: int, category_name: string}>  $achievements
     */
    private function traverseCategory(
        int $categoryId,
        string $rootCategoryName,
        ?int $currentExpansionId,
        array &$achievements,
    ): void {
        $this->delayRequest();

        $category = $this->fetchWithRetry('data/wow/achievement-category/'.$categoryId);
        if (! $category) {
            return;
        }

        /** @var string $categoryName */
        $categoryName = $category['name'] ?? '';
        $matched = ExpansionTierMatcher::match($categoryName);
        if ($matched !== null) {
            $currentExpansionId = $matched;
        }

        /** @var list<array{id: int, name: string}> $categoryAchievements */
        $categoryAchievements = $category['achievements'] ?? [];
        foreach ($categoryAchievements as $categoryAchievement) {
            $achievements[] = [
                'id' => $categoryAchievement['id'],
                'name_fr' => $categoryAchievement['name'],
                'expansion_id' => $currentExpansionId ?? 0,
                'category_name' => $rootCategoryName,
            ];
        }

        /** @var list<array{id: int}> $subcategories */
        $subcategories = $category['subcategories'] ?? [];
        foreach ($subcategories as $subcategory) {
            $this->traverseCategory($subcategory['id'], $rootCategoryName, $currentExpansionId, $achievements);
        }
    }
}
