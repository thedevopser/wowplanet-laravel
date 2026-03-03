<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\Support\Db2CsvLoader;
use App\Infrastructure\Parsers\SimpleArmoryParser;
use App\Models\WowAchievement;
use Illuminate\Support\Facades\Log;

final class AchievementImporter
{
    private const FALLBACK_EXPANSION_ID = 0;

    public function import(): void
    {
        $saAchievements = $this->loadSimpleArmoryData();
        if ($saAchievements === []) {
            return;
        }

        $frenchNames = $this->loadFrenchNames();
        $rows = $this->buildRows($saAchievements, $frenchNames);

        $this->saveRows($rows);
    }

    /**
     * @return array<int, array{category: string, subcategory: string, expansion_id: int|null, icon: string, points: int, faction: string|null}>
     */
    private function loadSimpleArmoryData(): array
    {
        $this->info('Parsing SimpleArmory achievements.json...');

        $achievements = SimpleArmoryParser::parseAchievements();
        if ($achievements === []) {
            $this->info('ERROR: Could not parse achievements.json.');

            return [];
        }

        $this->info(sprintf('  Found %d achievements in SimpleArmory.', count($achievements)));

        return $achievements;
    }

    /**
     * @return array<int, string>
     */
    private function loadFrenchNames(): array
    {
        $this->info('Loading French names from achievement.csv...');

        $names = Db2CsvLoader::loadStringMapByHeaders('achievement.csv', 'ID', 'Title_lang');
        $this->info(sprintf('  Found %d French names.', count($names)));

        return $names;
    }

    /**
     * @param  array<int, array{category: string, subcategory: string, expansion_id: int|null, icon: string, points: int, faction: string|null}>  $saAchievements
     * @param  array<int, string>  $frenchNames
     * @return list<array{id: int, name_fr: string, expansion_id: int, category_name: string, icon_url: string|null, points: int, faction: string|null, is_active: bool}>
     */
    private function buildRows(array $saAchievements, array $frenchNames): array
    {
        $rows = [];
        $matched = 0;
        $fallbacks = 0;

        foreach ($saAchievements as $id => $achievement) {
            $nameFr = $frenchNames[$id] ?? null;
            if ($nameFr !== null) {
                $matched++;
            } else {
                $fallbacks++;
            }

            $rows[] = [
                'id' => $id,
                'name_fr' => $nameFr ?? sprintf('[EN] %s', $achievement['subcategory']),
                'expansion_id' => $achievement['expansion_id'] ?? self::FALLBACK_EXPANSION_ID,
                'category_name' => $achievement['category'],
                'icon_url' => SimpleArmoryParser::buildIconUrl($achievement['icon']),
                'points' => $achievement['points'],
                'faction' => $achievement['faction'],
                'is_active' => true,
            ];
        }

        $this->info(sprintf('  %d matched with French name, %d using English fallback.', $matched, $fallbacks));

        return $rows;
    }

    /**
     * @param  list<array{id: int, name_fr: string, expansion_id: int, category_name: string, icon_url: string|null, points: int, faction: string|null, is_active: bool}>  $rows
     */
    private function saveRows(array $rows): void
    {
        $this->info(sprintf('Saving %d achievements...', count($rows)));

        $count = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            WowAchievement::query()->upsert(
                $chunk,
                uniqueBy: ['id'],
                update: ['name_fr', 'expansion_id', 'category_name', 'icon_url', 'points', 'faction', 'is_active'],
            );
            $count += count($chunk);
            $this->info(sprintf('  Saved %d...', $count));
        }

        $this->info(sprintf('Achievement import complete: %d items.', $count));
    }

    private function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }

        Log::info($message);
    }
}
