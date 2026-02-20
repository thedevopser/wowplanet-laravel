<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Models\WowQuest;

class QuestImporter
{
    use ImportsFromBlizzardApi;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
    ) {}

    /**
     * @param  array<int, int>  $areaExpansionMap
     * @param  array<int, int>  $modernQuestOverrides
     * @param  array<int, string>  $questFactionMap
     * @param  array<int, string>  $zoneFactionMap
     */
    public function import(
        array $areaExpansionMap,
        array $modernQuestOverrides = [],
        array $questFactionMap = [],
        array $zoneFactionMap = [],
    ): void {
        $this->logImportStart($areaExpansionMap, $modernQuestOverrides, $questFactionMap, $zoneFactionMap);

        $index = $this->fetchWithRetry('data/wow/quest/area/index');
        if (! $index) {
            $this->info('ERROR: Could not fetch quest area index.');

            return;
        }

        /** @var list<array{id: int, name: string}> $areas */
        $areas = $index['areas'] ?? [];
        $this->info(sprintf('Found %d quest areas to process.', count($areas)));

        $stats = ['imported' => 0, 'overrides' => 0, 'db2' => 0, 'unmapped' => []];

        foreach ($areas as $i => $area) {
            $this->importArea($area, $areaExpansionMap, $modernQuestOverrides, $questFactionMap, $zoneFactionMap, $stats);
            $this->logAreaProgress($i, count($areas), $stats['imported']);
        }

        $this->logImportComplete($stats);
    }

    /**
     * @param  array{id: int, name: string}  $area
     * @param  array<int, int>  $areaExpansionMap
     * @param  array<int, int>  $modernQuestOverrides
     * @param  array<int, string>  $questFactionMap
     * @param  array<int, string>  $zoneFactionMap
     * @param  array{imported: int, overrides: int, db2: int, unmapped: array<string, int>}  $stats
     */
    private function importArea(
        array $area,
        array $areaExpansionMap,
        array $modernQuestOverrides,
        array $questFactionMap,
        array $zoneFactionMap,
        array &$stats,
    ): void {
        $this->delayRequest();

        $areaId = $area['id'];
        $areaDetail = $this->fetchWithRetry('data/wow/quest/area/'.$areaId);
        if (! $areaDetail) {
            return;
        }

        $areaName = $this->resolveAreaName($areaDetail, $area['name']);
        /** @var list<array{id: int, name: string|null}> $quests */
        $quests = $areaDetail['quests'] ?? [];
        if (empty($quests)) {
            return;
        }

        $areaExpansionId = $areaExpansionMap[$areaId] ?? null;
        if ($areaExpansionId === null) {
            $stats['unmapped'][$areaName] ??= count($quests);
            $areaExpansionId = 0;
        }

        foreach ($quests as $quest) {
            $this->importQuest($quest, $areaId, $areaName, $areaExpansionId, $modernQuestOverrides, $questFactionMap, $zoneFactionMap, $stats);
        }
    }

    /**
     * @param  array{id: int, name: string|null}  $quest
     * @param  array<int, int>  $modernQuestOverrides
     * @param  array<int, string>  $questFactionMap
     * @param  array<int, string>  $zoneFactionMap
     * @param  array{imported: int, overrides: int, db2: int, unmapped: array<string, int>}  $stats
     */
    private function importQuest(
        array $quest,
        int $areaId,
        string $areaName,
        int $areaExpansionId,
        array $modernQuestOverrides,
        array $questFactionMap,
        array $zoneFactionMap,
        array &$stats,
    ): void {
        $questName = $quest['name'] ?? '';
        if ($questName === '') {
            return;
        }

        $questId = $quest['id'];
        $expansionId = $this->resolveQuestExpansion($questId, $areaExpansionId, $modernQuestOverrides, $stats);

        WowQuest::query()->updateOrCreate(['id' => $questId], [
            'name_fr' => $questName,
            'expansion_id' => $expansionId,
            'zone_name' => $areaName,
            'faction' => $questFactionMap[$questId] ?? $zoneFactionMap[$areaId] ?? null,
            'is_active' => true,
        ]);
        $stats['imported']++;
    }

    /**
     * @param  array<int, int>  $modernQuestOverrides
     * @param  array{imported: int, overrides: int, db2: int, unmapped: array<string, int>}  $stats
     */
    private function resolveQuestExpansion(int $questId, int $areaExpansionId, array $modernQuestOverrides, array &$stats): int
    {
        $hasModernOverride = $areaExpansionId >= 10 && isset($modernQuestOverrides[$questId]);
        $ctExpansion = $hasModernOverride ? $modernQuestOverrides[$questId] : $areaExpansionId;
        $isOverridden = $hasModernOverride && $ctExpansion !== $areaExpansionId;

        if ($isOverridden) {
            $stats['overrides']++;

            return $ctExpansion;
        }

        $stats['db2']++;

        return $areaExpansionId;
    }

    /**
     * @param  array<string, mixed>  $areaDetail
     */
    private function resolveAreaName(array $areaDetail, string $fallbackName): string
    {
        /** @var string|array{name?: string}|null $areaField */
        $areaField = $areaDetail['area'] ?? null;

        return match (true) {
            is_string($areaField) => $areaField,
            is_array($areaField) => (string) ($areaField['name'] ?? $fallbackName),
            default => $fallbackName,
        };
    }

    /**
     * @param  array<int, string>  $reputationFactionMap
     */
    public function tagMirrorFactions(array $reputationFactionMap): void
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
            $result = $this->resolvePairFactions($pair, $reputationFactionMap);

            if ($result === null) {
                $errors++;
                $this->info(sprintf('  [ERR] %s (IDs: %d, %d) — API error.', $pair['name'], $pair['id_a'], $pair['id_b']));

                continue;
            }

            if ($result === false) {
                $skipped++;
                $this->info(sprintf('  [SKIP] %s (IDs: %d, %d) — no faction reputation.', $pair['name'], $pair['id_a'], $pair['id_b']));

                continue;
            }

            $this->info(sprintf('  [TAG] %s → %d=%s, %d=%s', $pair['name'], $pair['id_a'], $result['faction_a'], $pair['id_b'], $result['faction_b']));
            WowQuest::query()->where('id', $pair['id_a'])->update(['faction' => $result['faction_a']]);
            WowQuest::query()->where('id', $pair['id_b'])->update(['faction' => $result['faction_b']]);
            $tagged++;

            if (($i + 1) % 20 === 0) {
                $this->info(sprintf('  Progress: %d/%d pairs.', $i + 1, count($pairs)));
            }
        }

        $this->info(sprintf('Mirror tagging complete: %d tagged, %d skipped, %d errors.', $tagged, $skipped, $errors));
    }

    /**
     * @param  array{id_a: int, id_b: int, name: string, zone: string}  $pair
     * @param  array<int, string>  $reputationFactionMap
     * @return array{faction_a: string, faction_b: string}|false|null null=API error, false=no faction
     */
    private function resolvePairFactions(array $pair, array $reputationFactionMap): array|false|null
    {
        $this->delayRequest();
        $detailA = $this->fetchWithRetry('data/wow/quest/'.$pair['id_a']);
        $factionFromA = ($detailA !== null) ? $this->detectFactionFromReputations($detailA, $reputationFactionMap) : null;

        if ($factionFromA !== null) {
            return [
                'faction_a' => $factionFromA,
                'faction_b' => $factionFromA === 'Alliance' ? 'Horde' : 'Alliance',
            ];
        }

        $this->delayRequest();
        $detailB = $this->fetchWithRetry('data/wow/quest/'.$pair['id_b']);
        $factionFromB = ($detailB !== null) ? $this->detectFactionFromReputations($detailB, $reputationFactionMap) : null;

        if ($factionFromB !== null) {
            return [
                'faction_a' => $factionFromB === 'Alliance' ? 'Horde' : 'Alliance',
                'faction_b' => $factionFromB,
            ];
        }

        if ($detailA === null && $detailB === null) {
            return null;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $questDetail
     * @param  array<int, string>  $reputationFactionMap
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

    /**
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
            $key = $quest->name_fr.'|||'.$quest->zone_name;
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
     * @param  array<int, int>  $areaExpansionMap
     * @param  array<int, int>  $modernQuestOverrides
     * @param  array<int, string>  $questFactionMap
     * @param  array<int, string>  $zoneFactionMap
     */
    private function logImportStart(array $areaExpansionMap, array $modernQuestOverrides, array $questFactionMap, array $zoneFactionMap): void
    {
        $this->info('Fetching quest area index...');
        $this->info('  DB2 area→expansion entries: '.count($areaExpansionMap));
        $this->info('  Modern per-quest overrides: '.count($modernQuestOverrides).' entries (ContentTuning ≥ 10)');
        $this->info('  Quest faction map: '.count($questFactionMap).' faction-specific quests');
        $this->info('  Zone faction map: '.count($zoneFactionMap).' faction-specific zones');
    }

    private function logAreaProgress(int $index, int $totalAreas, int $totalImported): void
    {
        if (($index + 1) % 50 === 0 || ($index + 1) === $totalAreas) {
            $this->info('  Areas: '.($index + 1).sprintf('/%d | Quests: %d', $totalAreas, $totalImported));
        }
    }

    /**
     * @param  array{imported: int, overrides: int, db2: int, unmapped: array<string, int>}  $stats
     */
    private function logImportComplete(array $stats): void
    {
        $this->info(sprintf('  Mapping: %d DB2-based | %d modern quest overrides (ContentTuning)', $stats['db2'], $stats['overrides']));

        if ($stats['unmapped'] !== []) {
            $this->info('  WARNING: Areas not in DB2 AreaTable (defaulted to Classic): '.count($stats['unmapped']));
            foreach ($stats['unmapped'] as $zone => $count) {
                $this->info(sprintf('    - %s (%d quests)', $zone, $count));
            }
        }

        $this->info(sprintf('Quest import complete: %d quests.', $stats['imported']));
    }
}
