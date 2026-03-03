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
     * @param  array<int, int>  $areaExpansionMap  [areaId => expansionId]
     * @param  array<int, int>  $questExpansionMap  [questId => expansionId] from ContentTuning
     * @param  array<int, string>  $questFactionMap  [questId => 'Alliance'|'Horde']
     * @param  array<int, string>  $zoneFactionMap  [areaId => 'Alliance'|'Horde']
     */
    public function import(
        array $areaExpansionMap,
        array $questExpansionMap = [],
        array $questFactionMap = [],
        array $zoneFactionMap = [],
    ): void {
        $this->info('Fetching quest area index from Blizzard API...');
        $this->info(sprintf('  Area expansion map: %d entries', count($areaExpansionMap)));
        $this->info(sprintf('  Quest expansion map (ContentTuning): %d entries', count($questExpansionMap)));
        $this->info(sprintf('  Quest faction map: %d entries', count($questFactionMap)));
        $this->info(sprintf('  Zone faction map: %d entries', count($zoneFactionMap)));

        $index = $this->fetchWithRetry('data/wow/quest/area/index');
        if ($index === null) {
            $this->info('ERROR: Could not fetch quest area index.');

            return;
        }

        /** @var list<array{id: int, name: string}> $areas */
        $areas = $index['areas'] ?? [];
        $this->info(sprintf('Found %d quest areas. Fetching details concurrently...', count($areas)));

        $areaDetails = $this->fetchAreaDetailsConcurrently($areas);
        $this->info(sprintf('Fetched %d area details. Building quest rows...', count(array_filter($areaDetails))));

        $rows = $this->buildQuestRows($areas, $areaDetails, $areaExpansionMap, $questExpansionMap, $questFactionMap, $zoneFactionMap);
        $this->info(sprintf('Built %d quest rows. Saving to database...', count($rows)));

        $this->saveQuests($rows);
    }

    /**
     * @param  list<array{id: int, name: string}>  $areas
     * @return array<int|string, array<string, mixed>|null>
     */
    private function fetchAreaDetailsConcurrently(array $areas): array
    {
        $endpoints = [];
        foreach ($areas as $area) {
            $endpoints[$area['id']] = 'data/wow/quest/area/'.$area['id'];
        }

        return $this->fetchBatchAsync($endpoints);
    }

    /**
     * @param  list<array{id: int, name: string}>  $areas
     * @param  array<int|string, array<string, mixed>|null>  $areaDetails
     * @param  array<int, int>  $areaExpansionMap
     * @param  array<int, int>  $questExpansionMap
     * @param  array<int, string>  $questFactionMap
     * @param  array<int, string>  $zoneFactionMap
     * @return list<array{id: int, name_fr: string, expansion_id: int, zone_name: string|null, faction: string|null}>
     */
    private function buildQuestRows(
        array $areas,
        array $areaDetails,
        array $areaExpansionMap,
        array $questExpansionMap,
        array $questFactionMap,
        array $zoneFactionMap,
    ): array {
        /** @var array<string, string> $areaNameFallback */
        $areaNameFallback = [];
        foreach ($areas as $area) {
            $areaNameFallback[$area['id']] = $area['name'];
        }

        $rows = [];
        $mapped = 0;
        $withZone = 0;

        foreach ($areaDetails as $areaId => $detail) {
            if ($detail === null) {
                continue;
            }

            $areaName = $this->resolveAreaName($detail, $areaNameFallback[$areaId] ?? '');
            $areaExpansionId = $areaExpansionMap[$areaId] ?? 0;

            /** @var list<array{id: int, name?: string}> $quests */
            $quests = $detail['quests'] ?? [];

            foreach ($quests as $quest) {
                $questName = $quest['name'] ?? '';
                if ($questName === '') {
                    continue;
                }

                $questId = $quest['id'];
                $expansionId = $questExpansionMap[$questId] ?? $areaExpansionId;
                $faction = $questFactionMap[$questId] ?? $zoneFactionMap[$areaId] ?? null;

                if ($expansionId > 0) {
                    $mapped++;
                }

                if ($areaName !== '') {
                    $withZone++;
                }

                $rows[] = [
                    'id' => $questId,
                    'name_fr' => $questName,
                    'expansion_id' => $expansionId,
                    'zone_name' => $areaName !== '' ? $areaName : null,
                    'faction' => $faction,
                ];
            }
        }

        $this->info(sprintf('  %d with expansion, %d with zone.', $mapped, $withZone));

        return $rows;
    }

    /**
     * @param  list<array{id: int, name_fr: string, expansion_id: int, zone_name: string|null, faction: string|null}>  $rows
     */
    private function saveQuests(array $rows): void
    {
        $count = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            $upsertData = array_map(fn (array $row): array => [
                'id' => $row['id'],
                'name_fr' => $row['name_fr'],
                'expansion_id' => $row['expansion_id'],
                'zone_name' => $row['zone_name'],
                'faction' => $row['faction'],
                'is_active' => true,
            ], $chunk);

            WowQuest::query()->upsert($upsertData, uniqueBy: ['id'], update: [
                'name_fr', 'expansion_id', 'zone_name', 'faction', 'is_active',
            ]);

            $count += count($chunk);
            $this->info(sprintf('  Saved %d/%d...', $count, count($rows)));
        }

        $this->info(sprintf('Quest import complete: %d quests.', $count));
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

        $questDetails = $this->fetchQuestDetailsConcurrently($pairs);
        $this->resolvePairsFromCache($pairs, $questDetails, $reputationFactionMap);
    }

    /**
     * @param  list<array{id_a: int, id_b: int, name: string, zone: string}>  $pairs
     * @return array<int|string, array<string, mixed>|null>
     */
    private function fetchQuestDetailsConcurrently(array $pairs): array
    {
        $endpoints = [];
        foreach ($pairs as $pair) {
            $endpoints[$pair['id_a']] = 'data/wow/quest/'.$pair['id_a'];
            $endpoints[$pair['id_b']] = 'data/wow/quest/'.$pair['id_b'];
        }

        $this->info(sprintf('  Fetching %d unique quest details concurrently (batches of %d)...', count($endpoints), self::CONCURRENT_BATCH_SIZE));

        return $this->fetchBatchAsync($endpoints);
    }

    /**
     * @param  list<array{id_a: int, id_b: int, name: string, zone: string}>  $pairs
     * @param  array<int|string, array<string, mixed>|null>  $questDetails
     * @param  array<int, string>  $reputationFactionMap
     */
    private function resolvePairsFromCache(array $pairs, array $questDetails, array $reputationFactionMap): void
    {
        $tagged = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($pairs as $pair) {
            $detailA = $questDetails[$pair['id_a']] ?? null;
            $detailB = $questDetails[$pair['id_b']] ?? null;

            if ($detailA === null && $detailB === null) {
                $notFound++;

                continue;
            }

            $factionFromA = ($detailA !== null) ? $this->detectFactionFromReputations($detailA, $reputationFactionMap) : null;
            $factionFromB = ($detailB !== null) ? $this->detectFactionFromReputations($detailB, $reputationFactionMap) : null;
            $resolvedFaction = $factionFromA ?? $factionFromB;

            if ($resolvedFaction === null) {
                $skipped++;

                continue;
            }

            $mirrorFaction = $resolvedFaction === 'Alliance' ? 'Horde' : 'Alliance';
            $factionA = $factionFromA ?? $mirrorFaction;
            $factionB = $factionFromB ?? $mirrorFaction;

            $this->info(sprintf('  [TAG] %s → %d=%s, %d=%s', $pair['name'], $pair['id_a'], $factionA, $pair['id_b'], $factionB));
            WowQuest::query()->where('id', $pair['id_a'])->update(['faction' => $factionA]);
            WowQuest::query()->where('id', $pair['id_b'])->update(['faction' => $factionB]);
            $tagged++;
        }

        $this->info(sprintf('Mirror tagging complete: %d tagged, %d no reputation data, %d not found in API.', $tagged, $skipped, $notFound));
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
        /** @var array<string, list<int>> $groups */
        $groups = [];

        foreach (WowQuest::query()->where('is_active', true)->whereNull('faction')->lazy() as $lazyCollection) {
            $key = $lazyCollection->name_fr.'|||'.$lazyCollection->zone_name;
            $groups[$key][] = $lazyCollection->id;
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
}
