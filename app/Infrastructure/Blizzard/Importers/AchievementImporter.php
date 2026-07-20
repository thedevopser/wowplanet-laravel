<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Parsers\SimpleArmoryParser;
use App\Models\WowAchievement;

final readonly class AchievementImporter
{
    use ImportsFromBlizzardApi;

    private const FALLBACK_EXPANSION_ID = 0;

    public function __construct(
        BlizzardApiClient $blizzardApiClient,
    ) {
        $this->blizzardApiClient = $blizzardApiClient;
    }

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
     * Noms français depuis l'index Achievement de l'API officielle.
     *
     * @return array<int, string>
     */
    private function loadFrenchNames(): array
    {
        $this->info('Fetching achievement index from Blizzard API...');

        $index = $this->fetchWithRetry('data/wow/achievement/index');
        if ($index === null) {
            $this->info('  WARNING: achievement index unavailable, falling back to English names.');

            return [];
        }

        $names = [];

        /** @var list<array{id?: int, name?: string}> $achievements */
        $achievements = $index['achievements'] ?? [];
        foreach ($achievements as $achievement) {
            $id = (int) ($achievement['id'] ?? 0);
            $name = trim($achievement['name'] ?? '');
            if ($id > 0 && $name !== '') {
                $names[$id] = $name;
            }
        }

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
}
