<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Models\WowQuest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Sleep;

class QuestImporter
{
    use ImportsFromBlizzardApi;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
    ) {}

    /**
     * @param  array<int, int>  $questExpansionMap  [questId => expansionId]
     * @param  array<int, string>  $questZoneMap  [questId => zoneName]
     * @param  array<int, string>  $questFactionMap  [questId => 'Alliance'|'Horde']
     */
    public function import(
        array $questExpansionMap,
        array $questZoneMap = [],
        array $questFactionMap = [],
    ): void {
        $this->info('Loading quests from DB2 CSV data...');
        $this->info(sprintf('  Expansion map: %d entries', count($questExpansionMap)));
        $this->info(sprintf('  Zone map: %d entries', count($questZoneMap)));
        $this->info(sprintf('  Faction map: %d entries', count($questFactionMap)));

        $quests = $this->parseQuestCsv();
        if ($quests === []) {
            $this->info('ERROR: quest_v2_cli_task.csv not found or empty.');

            return;
        }

        $this->info(sprintf('Found %d quests in CSV.', count($quests)));

        $count = 0;
        $mapped = 0;
        $withZone = 0;

        foreach ($quests as $quest) {
            $questId = $quest['id'];
            $expansionId = $questExpansionMap[$questId] ?? 0;
            $zoneName = $questZoneMap[$questId] ?? null;
            $faction = $questFactionMap[$questId] ?? null;

            if ($expansionId > 0) {
                $mapped++;
            }

            if ($zoneName !== null) {
                $withZone++;
            }

            WowQuest::query()->updateOrCreate(['id' => $questId], [
                'name_fr' => $quest['name_fr'],
                'expansion_id' => $expansionId,
                'zone_name' => $zoneName,
                'faction' => $faction,
                'is_active' => true,
            ]);
            $count++;
            if ($count % 2000 === 0) {
                $this->info(sprintf('  Saved %d...', $count));
            }
        }

        $this->info(sprintf(
            'Quest import complete: %d total (%d with expansion, %d with zone, %d without zone).',
            $count,
            $mapped,
            $withZone,
            $count - $withZone,
        ));
    }

    /**
     * @return list<array{id: int, name_fr: string}>
     */
    private function parseQuestCsv(): array
    {
        $csvPath = storage_path('app/blizzard/quest_v2_cli_task.csv');
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
        $nameIdx = (int) array_search('QuestTitle_lang', $headers, true);

        $quests = [];
        $skipped = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $name = trim($row[$nameIdx] ?? '');
            if ($name === '') {
                $skipped++;

                continue;
            }

            $quests[] = [
                'id' => (int) $row[$idIdx],
                'name_fr' => $name,
            ];
        }

        fclose($handle);

        if ($skipped > 0) {
            $this->info(sprintf('  Skipped %d quests with empty names.', $skipped));
        }

        return $quests;
    }

    private const BATCH_SIZE = 50;

    private const BATCH_PAUSE_S = 3;

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

        $questDetails = $this->fetchQuestDetailsInBatches($pairs);
        $this->resolvePairsFromCache($pairs, $questDetails, $reputationFactionMap);
    }

    /**
     * @param  list<array{id_a: int, id_b: int, name: string, zone: string}>  $pairs
     * @return array<int, array<string, mixed>|null>
     */
    private function fetchQuestDetailsInBatches(array $pairs): array
    {
        $questIds = [];
        foreach ($pairs as $pair) {
            $questIds[$pair['id_a']] = true;
            $questIds[$pair['id_b']] = true;
        }

        $questIds = array_keys($questIds);

        $this->info(sprintf('  Fetching %d unique quest details in batches of %d...', count($questIds), self::BATCH_SIZE));

        $details = [];
        $batches = array_chunk($questIds, self::BATCH_SIZE);

        foreach ($batches as $batchIndex => $batch) {
            if ($batchIndex > 0) {
                $this->info(sprintf('  Batch pause (%ds)...', self::BATCH_PAUSE_S));
                Sleep::sleep(self::BATCH_PAUSE_S);
            }

            foreach ($batch as $questId) {
                $this->delayRequest();
                $details[$questId] = $this->fetchWithRetry('data/wow/quest/'.$questId);
            }

            $this->info(sprintf('  Batch %d/%d complete (%d quests fetched).', $batchIndex + 1, count($batches), count($batch)));
        }

        return $details;
    }

    /**
     * @param  list<array{id_a: int, id_b: int, name: string, zone: string}>  $pairs
     * @param  array<int, array<string, mixed>|null>  $questDetails
     * @param  array<int, string>  $reputationFactionMap
     */
    private function resolvePairsFromCache(array $pairs, array $questDetails, array $reputationFactionMap): void
    {
        $tagged = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($pairs as $pair) {
            $detailA = $questDetails[$pair['id_a']] ?? null;
            $detailB = $questDetails[$pair['id_b']] ?? null;

            $factionFromA = ($detailA !== null) ? $this->detectFactionFromReputations($detailA, $reputationFactionMap) : null;
            $factionFromB = ($detailB !== null) ? $this->detectFactionFromReputations($detailB, $reputationFactionMap) : null;

            if ($factionFromA !== null) {
                $factionA = $factionFromA;
                $factionB = $factionFromA === 'Alliance' ? 'Horde' : 'Alliance';
            } elseif ($factionFromB !== null) {
                $factionB = $factionFromB;
                $factionA = $factionFromB === 'Alliance' ? 'Horde' : 'Alliance';
            } elseif ($detailA === null && $detailB === null) {
                $errors++;
                $this->info(sprintf('  [ERR] %s (IDs: %d, %d) — API error.', $pair['name'], $pair['id_a'], $pair['id_b']));

                continue;
            } else {
                $skipped++;
                $this->info(sprintf('  [SKIP] %s (IDs: %d, %d) — no faction reputation.', $pair['name'], $pair['id_a'], $pair['id_b']));

                continue;
            }

            $this->info(sprintf('  [TAG] %s → %d=%s, %d=%s', $pair['name'], $pair['id_a'], $factionA, $pair['id_b'], $factionB));
            WowQuest::query()->where('id', $pair['id_a'])->update(['faction' => $factionA]);
            WowQuest::query()->where('id', $pair['id_b'])->update(['faction' => $factionB]);
            $tagged++;
        }

        $this->info(sprintf('Mirror tagging complete: %d tagged, %d skipped, %d errors.', $tagged, $skipped, $errors));
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
}
