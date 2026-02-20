<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Models\WowQuest;
use Illuminate\Support\Facades\File;

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
}
