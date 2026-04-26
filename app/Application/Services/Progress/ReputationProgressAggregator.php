<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Infrastructure\Parsers\AddonDataParser;
use App\Infrastructure\Parsers\Db2FactionExpansionMapper;

class ReputationProgressAggregator
{
    public function __construct(
        private readonly Db2FactionExpansionMapper $db2FactionExpansionMapper,
        private readonly AddonDataParser $addonDataParser,
    ) {}

    /**
     * @param  array<string, mixed>  $reputationsResponse
     * @return array<int, array{total: int, completed: int, factions: list<array<string, mixed>>}>
     */
    public function aggregate(array $reputationsResponse, string $characterFaction = ''): array
    {
        $factionExpansionMap = $this->db2FactionExpansionMapper->build();
        $maxRenownMap = $this->db2FactionExpansionMapper->buildMaxRenownMap();
        $factionNamesMap = $this->db2FactionExpansionMapper->buildFactionNamesMap();
        $accountWideFactionIds = $this->db2FactionExpansionMapper->buildAccountWideFactionIds();
        $reputationFactionMap = $this->addonDataParser->getReputationFactionMap();

        /** @var list<array<string, mixed>> $reputations */
        $reputations = $reputationsResponse['reputations'] ?? [];

        /** @var array<int, list<array<string, mixed>>> $grouped */
        $grouped = [];

        /** @var array<int, true> $startedFactionIds */
        $startedFactionIds = [];

        foreach ($reputations as $reputation) {
            /** @var array{id?: int, name?: string} $faction */
            $faction = $reputation['faction'] ?? [];
            $factionId = (int) ($faction['id'] ?? 0);
            if ($factionId === 0) {
                continue;
            }

            if (! isset($factionExpansionMap[$factionId])) {
                continue;
            }

            /** @var array{raw?: int, value?: int, max?: int, tier?: int, name?: string, renown_level?: int} $standing */
            $standing = $reputation['standing'] ?? [];

            $expansionId = $factionExpansionMap[$factionId];

            $tier = (int) ($standing['tier'] ?? 0);
            $renownLevel = (int) ($standing['renown_level'] ?? 0);
            $max = (int) ($standing['max'] ?? 0);

            $completed = $this->isCompleted($factionId, $tier, $max, $renownLevel, $maxRenownMap);

            $grouped[$expansionId][] = [
                'id' => $factionId,
                'name' => (string) ($faction['name'] ?? ''),
                'standing_name' => (string) ($standing['name'] ?? ''),
                'tier' => $tier,
                'value' => (int) ($standing['value'] ?? 0),
                'max' => $max,
                'raw' => (int) ($standing['raw'] ?? 0),
                'renown_level' => $renownLevel,
                'completed' => $completed,
                'started' => true,
                'account_wide' => $renownLevel > 0 || isset($accountWideFactionIds[$factionId]),
            ];

            $startedFactionIds[$factionId] = true;
        }

        // Collect started faction names to avoid adding duplicates with different IDs
        /** @var array<string, true> $startedFactionNames */
        $startedFactionNames = [];
        foreach ($grouped as $factions) {
            foreach ($factions as $faction) {
                /** @var string $fName */
                $fName = $faction['name'];
                $startedFactionNames[$fName] = true;
            }
        }

        $unstartedNames = [];
        foreach ($factionExpansionMap as $factionId => $expansionId) {
            if (isset($startedFactionIds[$factionId])) {
                continue;
            }

            if ($this->isOppositeFaction($factionId, $characterFaction, $reputationFactionMap)) {
                continue;
            }

            $name = $factionNamesMap[$factionId] ?? '';

            // Skip if a faction with the same name is already started (duplicate faction ID)
            if (isset($startedFactionNames[$name])) {
                continue;
            }

            // Skip if an unstarted faction with the same name was already added
            if (isset($unstartedNames[$name])) {
                continue;
            }

            $unstartedNames[$name] = true;

            $grouped[$expansionId][] = [
                'id' => $factionId,
                'name' => $name,
                'standing_name' => 'Non commencée',
                'tier' => -1,
                'value' => 0,
                'max' => 0,
                'raw' => 0,
                'renown_level' => 0,
                'completed' => false,
                'started' => false,
                'account_wide' => isset($accountWideFactionIds[$factionId]),
            ];
        }

        return $this->buildExpansionProgress($grouped);
    }

    /**
     * @param  array<int, int>  $maxRenownMap
     */
    private function isCompleted(int $factionId, int $tier, int $max, int $renownLevel, array $maxRenownMap): bool
    {
        // Renown au cap : renown reste à max>0 par palier, on compare via DB2
        if ($renownLevel > 0 && isset($maxRenownMap[$factionId]) && $renownLevel >= $maxRenownMap[$factionId]) {
            return true;
        }

        // Tous les autres systèmes (exalted, friendship maxée, paragon, "Niveau X" Midnight) :
        // l'API renvoie max=0 quand la progression est au cap.
        return $max === 0 && $tier > 0;
    }

    /**
     * @param  array<int, string>  $reputationFactionMap
     */
    private function isOppositeFaction(int $factionId, string $characterFaction, array $reputationFactionMap): bool
    {
        if ($characterFaction === '') {
            return false;
        }

        $requiredFaction = $reputationFactionMap[$factionId] ?? null;

        return $requiredFaction !== null && $requiredFaction !== $characterFaction;
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $grouped
     * @return array<int, array{total: int, completed: int, factions: list<array<string, mixed>>}>
     */
    private function buildExpansionProgress(array $grouped): array
    {
        $results = [];

        for ($i = 0; $i <= 11; $i++) {
            $factions = $grouped[$i] ?? [];
            $completed = 0;

            foreach ($factions as $faction) {
                if ($faction['completed'] === true) {
                    $completed++;
                }
            }

            $results[$i] = [
                'total' => count($factions),
                'completed' => $completed,
                'factions' => $factions,
            ];
        }

        return $results;
    }
}
