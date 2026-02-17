<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use App\Models\WowAchievement;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowQuest;
use Illuminate\Support\Facades\Log;

class BlizzardBatchImporter
{
    private const REQUEST_DELAY_MS = 150;
    private const RATE_LIMIT_WAIT_S = 10;
    private const MAX_RETRIES = 3;

    public function __construct(private BlizzardApiClient $apiClient) {}

    /**
     * Import ALL achievements by traversing the Blizzard category tree.
     * Expansion is determined from addon total_ids mapping.
     *
     * @param array<int, int> $addonExpansionMap [achievement_id => expansion_id] from addon data
     */
    public function importAchievements(array $addonExpansionMap = []): void
    {
        $this->info("Fetching achievement category tree...");

        $index = $this->fetchWithRetry('data/wow/achievement-category/index');
        if (!$index) {
            $this->info("ERROR: Could not fetch achievement category index.");
            return;
        }

        $rootCategories = $index['root_categories'] ?? [];
        $this->info("Found " . count($rootCategories) . " root categories.");
        $this->info("Addon expansion map: " . count($addonExpansionMap) . " achievement IDs.");

        $achievements = [];

        foreach ($rootCategories as $rootCat) {
            $this->info("  Traversing: {$rootCat['name']}");
            $this->traverseAchievementCategory(
                $rootCat['id'],
                $rootCat['name'],
                null,
                $achievements,
            );
        }

        $this->info("Saving " . count($achievements) . " achievements to database...");
        $mapped = 0;
        $unmapped = 0;
        $count = 0;

        foreach ($achievements as $ach) {
            // Use addon map for expansion, fallback to category-based detection
            $expansionId = $addonExpansionMap[$ach['id']] ?? $ach['expansion_id'];
            if (isset($addonExpansionMap[$ach['id']])) {
                $mapped++;
            } else {
                $unmapped++;
            }

            WowAchievement::updateOrCreate(
                ['id' => $ach['id']],
                [
                    'name_fr' => $ach['name_fr'],
                    'expansion_id' => $expansionId,
                    'category_name' => $ach['category_name'],
                    'is_active' => true,
                ]
            );
            $count++;
            if ($count % 500 === 0) {
                $this->info("  Saved {$count}...");
            }
        }

        $this->info("Achievement import complete: {$count} total ({$mapped} mapped via addon, {$unmapped} from category tree).");
    }

    /**
     * Import ALL quests from Blizzard quest area endpoints.
     *
     * Expansion mapping strategy:
     * 1. DB2-based area→expansion map (AreaTable + Map + ContentTuning) is the primary source
     * 2. Per-quest override for modern zones (expansion >= 10): if ContentTuning
     *    says a different modern expansion, use it (handles TWW/Midnight splits)
     *
     * @param array<int, int> $areaExpansionMap [area_id => expansion_id] from DB2 data
     * @param array<int, int> $modernQuestOverrides [quest_id => expansion_id] only for expansion >= 10
     */
    public function importQuests(array $areaExpansionMap, array $modernQuestOverrides = []): void
    {
        $this->info("Fetching quest area index...");
        $this->info("  DB2 area→expansion entries: " . count($areaExpansionMap));
        $this->info("  Modern per-quest overrides: " . count($modernQuestOverrides) . " entries (ContentTuning ≥ 10)");

        $index = $this->fetchWithRetry('data/wow/quest/area/index');
        if (!$index) {
            $this->info("ERROR: Could not fetch quest area index.");
            return;
        }

        $areas = $index['areas'] ?? [];
        $totalAreas = count($areas);
        $this->info("Found {$totalAreas} quest areas to process.");

        $totalImported = 0;
        $unmappedAreas = [];
        $overrideCount = 0;
        $db2Count = 0;

        foreach ($areas as $i => $area) {
            usleep(self::REQUEST_DELAY_MS * 1000);

            $areaId = $area['id'];
            $areaDetail = $this->fetchWithRetry("data/wow/quest/area/{$areaId}");
            if (!$areaDetail) continue;

            $areaName = is_string($areaDetail['area'] ?? null)
                ? $areaDetail['area']
                : ($areaDetail['area']['name'] ?? $area['name'] ?? "Zone #{$areaId}");

            $quests = $areaDetail['quests'] ?? [];
            if (empty($quests)) continue;

            // Resolve area expansion from DB2 data
            $areaExpansionId = $areaExpansionMap[$areaId] ?? null;

            if ($areaExpansionId === null) {
                $unmappedAreas[$areaName] = ($unmappedAreas[$areaName] ?? 0) + count($quests);
                $areaExpansionId = 0;
            }

            foreach ($quests as $quest) {
                $questName = $quest['name'] ?? null;
                if (!$questName) continue;

                $questId = $quest['id'];
                $expansionId = $areaExpansionId;

                // Per-quest override for modern expansions (≥ 10):
                // If zone is modern AND ContentTuning says a different modern expansion
                if ($areaExpansionId >= 10 && isset($modernQuestOverrides[$questId])) {
                    $ctExpansion = $modernQuestOverrides[$questId];
                    if ($ctExpansion !== $areaExpansionId) {
                        $expansionId = $ctExpansion;
                        $overrideCount++;
                    } else {
                        $db2Count++;
                    }
                } else {
                    $db2Count++;
                }

                WowQuest::updateOrCreate(
                    ['id' => $questId],
                    [
                        'name_fr' => $questName,
                        'expansion_id' => $expansionId,
                        'zone_name' => $areaName,
                        'is_active' => true,
                    ]
                );
                $totalImported++;
            }

            if (($i + 1) % 50 === 0 || ($i + 1) === $totalAreas) {
                $this->info("  Areas: " . ($i + 1) . "/{$totalAreas} | Quests: {$totalImported}");
            }
        }

        $this->info("  Mapping: {$db2Count} DB2-based | {$overrideCount} modern quest overrides (ContentTuning)");

        if (!empty($unmappedAreas)) {
            $this->info("  WARNING: Areas not in DB2 AreaTable (defaulted to Classic): " . count($unmappedAreas));
            foreach ($unmappedAreas as $zone => $count) {
                $this->info("    - {$zone} ({$count} quests)");
            }
        }

        $this->info("Quest import complete: {$totalImported} quests from {$totalAreas} areas.");
    }

    /**
     * Import Mounts from Blizzard Index.
     */
    public function importMounts(): void
    {
        $this->info("Fetching Mount Index...");
        $response = $this->fetchWithRetry('data/wow/mount/index');
        if (!$response) {
            $this->info("ERROR: Could not fetch mount index.");
            return;
        }

        $mounts = $response['mounts'] ?? [];
        $this->info("Found " . count($mounts) . " mounts.");

        foreach ($mounts as $mount) {
            WowMount::updateOrCreate(
                ['id' => $mount['id']],
                ['name_fr' => $mount['name'] ?? "Monture #{$mount['id']}", 'is_active' => true]
            );
        }

        $this->info("Mount import complete.");
    }

    /**
     * Import Pets from Blizzard Index.
     */
    public function importPets(): void
    {
        $this->info("Fetching Pet Index...");
        $response = $this->fetchWithRetry('data/wow/pet/index');
        if (!$response) {
            $this->info("ERROR: Could not fetch pet index.");
            return;
        }

        $pets = $response['pets'] ?? [];
        $this->info("Found " . count($pets) . " pets.");

        foreach ($pets as $pet) {
            WowPet::updateOrCreate(
                ['id' => $pet['id']],
                ['name_fr' => $pet['name'] ?? "Mascotte #{$pet['id']}", 'is_active' => true]
            );
        }

        $this->info("Pet import complete.");
    }

    // ===================== PRIVATE =====================

    private function traverseAchievementCategory(
        int $categoryId,
        string $rootCategoryName,
        ?int $currentExpansionId,
        array &$achievements,
    ): void {
        usleep(self::REQUEST_DELAY_MS * 1000);

        $category = $this->fetchWithRetry("data/wow/achievement-category/{$categoryId}");
        if (!$category) return;

        // Try to determine expansion from this category's name
        $matched = $this->matchExpansion($category['name'] ?? '');
        if ($matched !== null) {
            $currentExpansionId = $matched;
        }

        // Collect direct achievements
        foreach ($category['achievements'] ?? [] as $ach) {
            $achievements[] = [
                'id' => $ach['id'],
                'name_fr' => $ach['name'],
                'expansion_id' => $currentExpansionId ?? 0,
                'category_name' => $rootCategoryName,
            ];
        }

        // Recurse into sub-categories
        foreach ($category['subcategories'] ?? [] as $sub) {
            $this->traverseAchievementCategory(
                $sub['id'],
                $rootCategoryName,
                $currentExpansionId,
                $achievements,
            );
        }
    }

    /**
     * Match a French category/zone name to an expansion ID.
     * Most specific keywords first to avoid partial matches.
     */
    private function matchExpansion(string $name): ?int
    {
        $keywords = [
            // Multi-word (most specific first)
            'Royaumes de l\'Est' => 0,
            'Battle for Azeroth' => 7,
            'Mists of Pandaria' => 4,
            'Burning Crusade' => 1,
            'Lich King' => 2,
            'War Within' => 10,

            // Single-word identifiers
            'Kalimdor' => 0,
            'Classique' => 0,
            'Classic' => 0,
            'Outreterre' => 1,
            'Norfendre' => 2,
            'Northrend' => 2,
            'Cataclysm' => 3,
            'Cataclysme' => 3,
            'Pandarie' => 4,
            'Draenor' => 5,
            'Warlords' => 5,
            'Legion' => 6,
            'Légion' => 6,
            'Ombreterre' => 8,
            'Shadowlands' => 8,
            'Dragonflight' => 9,
            'Midnight' => 11,
        ];

        foreach ($keywords as $keyword => $expansionId) {
            if (mb_stripos($name, $keyword) !== false) {
                return $expansionId;
            }
        }

        return null;
    }

    private function fetchWithRetry(string $endpoint, int $attempt = 1): ?array
    {
        try {
            $region = config('services.blizzard.region', 'eu');
            return $this->apiClient->get($endpoint, [
                'namespace' => "static-{$region}",
            ]);
        } catch (\Exception $e) {
            if ($attempt < self::MAX_RETRIES && str_contains($e->getMessage(), '429')) {
                $delay = self::RATE_LIMIT_WAIT_S * $attempt;
                $this->info("Rate limit hit, waiting {$delay}s (attempt {$attempt}/" . self::MAX_RETRIES . ")...");
                sleep($delay);
                return $this->fetchWithRetry($endpoint, $attempt + 1);
            }
            if (str_contains($e->getMessage(), '404')) {
                return null;
            }
            Log::warning("API error [{$endpoint}]: " . $e->getMessage());
            return null;
        }
    }

    private function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message . PHP_EOL;
        }
        Log::info($message);
    }
}
