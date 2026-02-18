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

    public function __construct(private readonly BlizzardApiClient $blizzardApiClient)
    {
    }

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

        /** @var list<array{id: int, name: string}> $rootCategories */
        $rootCategories = $index['root_categories'] ?? [];
        $this->info("Found " . count($rootCategories) . " root categories.");
        $this->info("Addon expansion map: " . count($addonExpansionMap) . " achievement IDs.");

        $achievements = [];

        foreach ($rootCategories as $rootCategory) {
            $this->info('  Traversing: ' . $rootCategory['name']);
            $this->traverseAchievementCategory(
                $rootCategory['id'],
                $rootCategory['name'],
                null,
                $achievements,
            );
        }

        $this->info("Saving " . count($achievements) . " achievements to database...");
        $mapped = 0;
        $unmapped = 0;
        $count = 0;

        foreach ($achievements as $achievement) {
            // Use addon map for expansion, fallback to category-based detection
            $expansionId = $addonExpansionMap[$achievement['id']] ?? $achievement['expansion_id'];
            $mapped += isset($addonExpansionMap[$achievement['id']]) ? 1 : 0;
            $unmapped += isset($addonExpansionMap[$achievement['id']]) ? 0 : 1;

            WowAchievement::updateOrCreate(
                ['id' => $achievement['id']],
                [
                    'name_fr' => $achievement['name_fr'],
                    'expansion_id' => $expansionId,
                    'category_name' => $achievement['category_name'],
                    'is_active' => true,
                ]
            );
            $count++;
            if ($count % 500 === 0) {
                $this->info(sprintf('  Saved %d...', $count));
            }
        }

        $this->info(sprintf('Achievement import complete: %d total (%d mapped via addon, %d from category tree).', $count, $mapped, $unmapped));
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
     * @param array<int, string> $questFactionMap [quest_id => 'Alliance'|'Horde'] from FiltRaces
     * @param array<int, string> $zoneFactionMap [area_id => 'Alliance'|'Horde'] from FactionGroupMask
     */
    public function importQuests(
        array $areaExpansionMap,
        array $modernQuestOverrides = [],
        array $questFactionMap = [],
        array $zoneFactionMap = [],
    ): void {
        $this->info("Fetching quest area index...");
        $this->info("  DB2 area→expansion entries: " . count($areaExpansionMap));
        $this->info("  Modern per-quest overrides: " . count($modernQuestOverrides) . " entries (ContentTuning ≥ 10)");
        $this->info("  Quest faction map: " . count($questFactionMap) . " faction-specific quests");
        $this->info("  Zone faction map: " . count($zoneFactionMap) . " faction-specific zones");

        $index = $this->fetchWithRetry('data/wow/quest/area/index');
        if (!$index) {
            $this->info("ERROR: Could not fetch quest area index.");
            return;
        }

        /** @var list<array{id: int, name: string}> $areas */
        $areas = $index['areas'] ?? [];
        $totalAreas = count($areas);
        $this->info(sprintf('Found %d quest areas to process.', $totalAreas));

        $totalImported = 0;
        $unmappedAreas = [];
        $overrideCount = 0;
        $db2Count = 0;

        foreach ($areas as $i => $area) {
            \Illuminate\Support\Sleep::usleep(self::REQUEST_DELAY_MS * 1000);

            $areaId = $area['id'];
            $areaDetail = $this->fetchWithRetry('data/wow/quest/area/' . $areaId);
            if (!$areaDetail) {
                continue;
            }

            /** @var string|array{name?: string}|null $areaField */
            $areaField = $areaDetail['area'] ?? null;
            $areaName = match (true) {
                is_string($areaField) => $areaField,
                is_array($areaField) => (string) ($areaField['name'] ?? $area['name']),
                default => $area['name'],
            };

            /** @var list<array{id: int, name: string|null}> $quests */
            $quests = $areaDetail['quests'] ?? [];
            if (empty($quests)) {
                continue;
            }

            // Resolve area expansion from DB2 data
            $areaExpansionId = $areaExpansionMap[$areaId] ?? null;

            if ($areaExpansionId === null) {
                $unmappedAreas[$areaName] ??= count($quests);
                $areaExpansionId = 0;
            }

            foreach ($quests as $quest) {
                $questName = $quest['name'] ?? '';
                if ($questName === '') {
                    continue;
                }

                $questId = $quest['id'];
                $expansionId = $areaExpansionId;

                // Per-quest override for modern expansions (≥ 10):
                // If zone is modern AND ContentTuning says a different modern expansion
                $hasModernOverride = $areaExpansionId >= 10 && isset($modernQuestOverrides[$questId]);
                $ctExpansion = $hasModernOverride ? $modernQuestOverrides[$questId] : $areaExpansionId;
                $isOverridden = $hasModernOverride && $ctExpansion !== $areaExpansionId;

                if ($isOverridden) {
                    $expansionId = $ctExpansion;
                    $overrideCount++;
                }

                if (!$isOverridden) {
                    $db2Count++;
                }

                WowQuest::updateOrCreate(
                    ['id' => $questId],
                    [
                        'name_fr' => $questName,
                        'expansion_id' => $expansionId,
                        'zone_name' => $areaName,
                        'faction' => $questFactionMap[$questId] ?? $zoneFactionMap[$areaId] ?? null,
                        'is_active' => true,
                    ]
                );
                $totalImported++;
            }

            if (($i + 1) % 50 === 0 || ($i + 1) === $totalAreas) {
                $this->info("  Areas: " . ($i + 1) . sprintf('/%d | Quests: %d', $totalAreas, $totalImported));
            }
        }

        $this->info(sprintf('  Mapping: %d DB2-based | %d modern quest overrides (ContentTuning)', $db2Count, $overrideCount));

        if ($unmappedAreas !== []) {
            $this->info("  WARNING: Areas not in DB2 AreaTable (defaulted to Classic): " . count($unmappedAreas));
            foreach ($unmappedAreas as $zone => $count) {
                $this->info(sprintf('    - %s (%d quests)', $zone, $count));
            }
        }

        $this->info(sprintf('Quest import complete: %d quests from %d areas.', $totalImported, $totalAreas));
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

        /** @var list<array{id: int, name: string}> $mounts */
        $mounts = $response['mounts'] ?? [];
        $this->info("Found " . count($mounts) . " mounts.");

        foreach ($mounts as $mount) {
            WowMount::updateOrCreate(
                ['id' => $mount['id']],
                ['name_fr' => $mount['name'], 'is_active' => true]
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

        /** @var list<array{id: int, name: string}> $pets */
        $pets = $response['pets'] ?? [];
        $this->info("Found " . count($pets) . " pets.");

        foreach ($pets as $pet) {
            WowPet::updateOrCreate(
                ['id' => $pet['id']],
                ['name_fr' => $pet['name'], 'is_active' => true]
            );
        }

        $this->info("Pet import complete.");
    }

    /**
     * Tag mirror quest pairs (same name+zone, no faction) by checking API reputation rewards.
     *
     * @param array<int, string> $reputationFactionMap [reputation_faction_id => 'Alliance'|'Horde']
     */
    public function tagMirrorQuestFactions(array $reputationFactionMap): void
    {
        $this->info('Tagging mirror quest pairs via API reputation rewards...');
        $pairs = $this->findMirrorPairs();
        $this->info(sprintf('  Found %d mirror pairs to process.', count($pairs)));

        if ($pairs === []) {
            $this->info('  No mirror pairs found.');
            return;
        }

        $tagged = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($pairs as $i => $pair) {
            $questIdA = $pair['id_a'];
            $questIdB = $pair['id_b'];
            $questName = $pair['name'];

            // Try quest A first
            \Illuminate\Support\Sleep::usleep(self::REQUEST_DELAY_MS * 1000);
            $detailA = $this->fetchWithRetry('data/wow/quest/' . $questIdA);
            $factionFromA = ($detailA !== null)
                ? $this->detectFactionFromReputations($detailA, $reputationFactionMap)
                : null;

            if ($factionFromA !== null) {
                $factionA = $factionFromA;
                $factionB = $factionA === 'Alliance' ? 'Horde' : 'Alliance';
            } else {
                // Try quest B
                \Illuminate\Support\Sleep::usleep(self::REQUEST_DELAY_MS * 1000);
                $detailB = $this->fetchWithRetry('data/wow/quest/' . $questIdB);
                $factionFromB = ($detailB !== null)
                    ? $this->detectFactionFromReputations($detailB, $reputationFactionMap)
                    : null;

                if ($factionFromB !== null) {
                    $factionB = $factionFromB;
                    $factionA = $factionB === 'Alliance' ? 'Horde' : 'Alliance';
                } else {
                    if ($detailA === null && $detailB === null) {
                        $this->info(sprintf('  [ERR] %s (IDs: %d, %d) — API error.', $questName, $questIdA, $questIdB));
                        $errors++;
                    } else {
                        $this->info(sprintf('  [SKIP] %s (IDs: %d, %d) — no faction reputation.', $questName, $questIdA, $questIdB));
                        $skipped++;
                    }

                    continue;
                }
            }

            $this->info(sprintf('  [TAG] %s → %d=%s, %d=%s', $questName, $questIdA, $factionA, $questIdB, $factionB));
            WowQuest::query()->where('id', $questIdA)->update(['faction' => $factionA]);
            WowQuest::query()->where('id', $questIdB)->update(['faction' => $factionB]);
            $tagged++;

            if (($i + 1) % 20 === 0) {
                $this->info(sprintf('  Progress: %d/%d pairs.', $i + 1, count($pairs)));
            }
        }

        $this->info(sprintf('Mirror tagging complete: %d tagged, %d skipped, %d errors.', $tagged, $skipped, $errors));
    }

    /**
     * Find pairs of untagged quests that share the same name + zone.
     *
     * @return list<array{id_a: int, id_b: int, name: string, zone: string}>
     */
    private function findMirrorPairs(): array
    {
        /** @var \Illuminate\Support\Collection<int, WowQuest> $untagged */
        $untagged = WowQuest::query()
            ->where('is_active', true)
            ->whereNull('faction')
            ->get(['id', 'name_fr', 'zone_name']);

        /** @var array<string, list<int>> $groups */
        $groups = [];
        foreach ($untagged as $quest) {
            $key = $quest->name_fr . '|||' . $quest->zone_name;
            $groups[$key][] = $quest->id;
        }

        $pairs = [];
        foreach ($groups as $key => $ids) {
            if (count($ids) < 2) {
                continue;
            }

            sort($ids);
            [$name, $zone] = explode('|||', $key);
            $pairs[] = [
                'id_a' => $ids[0],
                'id_b' => $ids[1],
                'name' => $name,
                'zone' => $zone,
            ];
        }

        return $pairs;
    }

    /**
     * Detect faction from quest reputation rewards.
     *
     * @param array<string, mixed> $questDetail
     * @param array<int, string> $reputationFactionMap
     */
    private function detectFactionFromReputations(array $questDetail, array $reputationFactionMap): ?string
    {
        /** @var array{reputations?: list<array{reward?: array{id: int}}>} $rewards */
        $rewards = $questDetail['rewards'] ?? [];
        /** @var list<array{reward?: array{id: int}}> $reputations */
        $reputations = $rewards['reputations'] ?? [];

        foreach ($reputations as $reputation) {
            $factionId = $reputation['reward']['id'] ?? null;
            if ($factionId !== null && isset($reputationFactionMap[$factionId])) {
                return $reputationFactionMap[$factionId];
            }
        }

        return null;
    }

    // ===================== PRIVATE =====================

    /**
     * @param list<array{id: int, name_fr: string, expansion_id: int, category_name: string}> $achievements
     */
    private function traverseAchievementCategory(
        int $categoryId,
        string $rootCategoryName,
        ?int $currentExpansionId,
        array &$achievements,
    ): void {
        \Illuminate\Support\Sleep::usleep(self::REQUEST_DELAY_MS * 1000);

        $category = $this->fetchWithRetry('data/wow/achievement-category/' . $categoryId);
        if (!$category) {
            return;
        }

        // Try to determine expansion from this category's name
        /** @var string $categoryName */
        $categoryName = $category['name'] ?? '';
        $matched = $this->matchExpansion($categoryName);
        if ($matched !== null) {
            $currentExpansionId = $matched;
        }

        // Collect direct achievements
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

        // Recurse into sub-categories
        /** @var list<array{id: int}> $subcategories */
        $subcategories = $category['subcategories'] ?? [];
        foreach ($subcategories as $subcategory) {
            $this->traverseAchievementCategory(
                $subcategory['id'],
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
            "Royaumes de l'Est" => 0,
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

    /**
     * @return array<string, mixed>|null
     */
    private function fetchWithRetry(string $endpoint, int $attempt = 1): ?array
    {
        try {
            /** @var string $region */
            $region = config('services.blizzard.region', 'eu');
            return $this->blizzardApiClient->get($endpoint, [
                'namespace' => 'static-' . $region,
            ]);
        } catch (\Exception $exception) {
            if ($attempt < self::MAX_RETRIES && str_contains($exception->getMessage(), '429')) {
                $delay = self::RATE_LIMIT_WAIT_S * $attempt;
                $this->info(sprintf('Rate limit hit, waiting %ds (attempt %d/', $delay, $attempt) . self::MAX_RETRIES . ")...");
                \Illuminate\Support\Sleep::sleep($delay);
                return $this->fetchWithRetry($endpoint, $attempt + 1);
            }

            if (str_contains($exception->getMessage(), '404')) {
                return null;
            }

            Log::warning(sprintf('API error [%s]: ', $endpoint) . $exception->getMessage());
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
