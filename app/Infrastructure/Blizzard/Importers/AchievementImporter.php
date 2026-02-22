<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Models\WowAchievement;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class AchievementImporter
{
    private const TRACKING_FLAGS_MASK = 0x120000;

    private function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }

        Log::info($message);
    }

    /**
     * Import achievements from DB2 CSV data combined with expansion mapping.
     *
     * @param  array<int, int>  $addonExpansionMap  [achievement_id => expansion_id]
     */
    public function import(array $addonExpansionMap = []): void
    {
        $this->info('Loading achievements from DB2 CSV data...');

        $categories = $this->parseCategoryCsv();
        if ($categories === []) {
            $this->info('ERROR: achievement_category.csv not found or empty.');

            return;
        }

        $result = $this->parseAchievementCsv($categories);
        if ($result['achievements'] === []) {
            $this->info('ERROR: achievement.csv not found or empty.');

            return;
        }

        $this->info(sprintf('Found %d achievements in CSV.', count($result['achievements'])));
        $this->info(sprintf('Expansion map covers %d achievement IDs.', count($addonExpansionMap)));

        $this->saveAchievements($result['achievements'], $addonExpansionMap, $result['trackingIds']);
    }

    /**
     * @param  list<array{id: int, name_fr: string, expansion_id: int, category_name: string}>  $achievements
     * @param  array<int, int>  $addonExpansionMap
     * @param  list<int>  $trackingIds
     */
    private function saveAchievements(array $achievements, array $addonExpansionMap, array $trackingIds): void
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

        $this->info(sprintf(
            'Achievement import complete: %d total (%d mapped via DB2 expansion data, %d with category-tree fallback).',
            $count,
            $mapped,
            $unmapped,
        ));

        // Deactivate any debug/internal achievements that were imported in previous runs
        $cleaned = WowAchievement::query()
            ->where(function (\Illuminate\Contracts\Database\Query\Builder $builder): void {
                $builder->where('name_fr', 'like', '<DNT>%')
                    ->orWhere('name_fr', 'like', '[HIDDEN]%')
                    ->orWhere('name_fr', 'like', '%[DNT]%');
            })
            ->update(['is_active' => false]);

        if ($cleaned > 0) {
            $this->info(sprintf('Deactivated %d debug/internal achievements.', $cleaned));
        }

        // Deactivate tracking achievements (bits 17+20, 0 points) not returned by Blizzard API
        if ($trackingIds !== []) {
            $deactivated = 0;
            foreach (array_chunk($trackingIds, 500) as $chunk) {
                $deactivated += WowAchievement::query()
                    ->whereIn('id', $chunk)
                    ->update(['is_active' => false]);
            }

            if ($deactivated > 0) {
                $this->info(sprintf('Deactivated %d tracking achievements (no API visibility).', $deactivated));
            }
        }
    }

    private function isInternalAchievement(string $title): bool
    {
        return str_starts_with($title, '<Hidden>')
            || str_starts_with($title, '<DNT>')
            || str_starts_with($title, '[HIDDEN]')
            || str_contains($title, '[DNT]');
    }

    /**
     * Walk up category hierarchy to find the root category name.
     *
     * @param  array<int, array{name: string, parent: int}>  $categories
     */
    private function getRootCategoryName(int $categoryId, array $categories): string
    {
        $visited = [];
        $rootName = '';

        while ($categoryId > 0 && ! isset($visited[$categoryId])) {
            $visited[$categoryId] = true;

            if (! isset($categories[$categoryId])) {
                break;
            }

            $rootName = $categories[$categoryId]['name'];
            $categoryId = $categories[$categoryId]['parent'];
        }

        return $rootName;
    }

    /**
     * @return array<int, array{name: string, parent: int}>
     */
    private function parseCategoryCsv(): array
    {
        $csvPath = storage_path('app/blizzard/achievement_category.csv');
        if (! File::exists($csvPath)) {
            return [];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $nameIdx = (int) array_search('Name_lang', $headers, true);
        $parentIdx = (int) array_search('Parent', $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $map[(int) $row[$idIdx]] = [
                'name' => (string) $row[$nameIdx],
                'parent' => (int) $row[$parentIdx],
            ];
        }

        fclose($handle);

        return $map;
    }

    /**
     * @param  array<int, array{name: string, parent: int}>  $categories
     * @return array{achievements: list<array{id: int, name_fr: string, expansion_id: int, category_name: string}>, trackingIds: list<int>}
     */
    private function parseAchievementCsv(array $categories): array
    {
        $csvPath = storage_path('app/blizzard/achievement.csv');
        if (! File::exists($csvPath)) {
            return ['achievements' => [], 'trackingIds' => []];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return ['achievements' => [], 'trackingIds' => []];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);

            return ['achievements' => [], 'trackingIds' => []];
        }

        $idIdx = (int) array_search('ID', $headers, true);
        $titleIdx = (int) array_search('Title_lang', $headers, true);
        $categoryIdx = (int) array_search('Category', $headers, true);
        $flagsIdx = (int) array_search('Flags', $headers, true);
        $pointsIdx = (int) array_search('Points', $headers, true);

        $achievements = [];
        $trackingIds = [];
        $skipped = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $title = trim($row[$titleIdx] ?? '');

            // Skip achievements with empty names or debug/internal names
            if ($title === '' || $this->isInternalAchievement($title)) {
                $skipped++;

                continue;
            }

            // Skip tracking achievements (bits 17+20 set, 0 points) — not returned by Blizzard API
            $flags = (int) ($row[$flagsIdx] ?? 0);
            $points = (int) ($row[$pointsIdx] ?? 0);
            if (($flags & self::TRACKING_FLAGS_MASK) === self::TRACKING_FLAGS_MASK && $points === 0) {
                $trackingIds[] = (int) $row[$idIdx];
                $skipped++;

                continue;
            }

            $categoryId = (int) $row[$categoryIdx];
            $rootCategory = $this->getRootCategoryName($categoryId, $categories);

            $achievements[] = [
                'id' => (int) $row[$idIdx],
                'name_fr' => $title,
                'expansion_id' => 0,
                'category_name' => $rootCategory !== '' ? $rootCategory : 'Inconnu',
            ];
        }

        fclose($handle);

        if ($skipped > 0) {
            $this->info(sprintf('  Skipped %d achievements with empty/hidden/tracking names.', $skipped));
        }

        return ['achievements' => $achievements, 'trackingIds' => $trackingIds];
    }
}
