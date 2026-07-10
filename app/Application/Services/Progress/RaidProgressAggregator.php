<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

class RaidProgressAggregator
{
    /**
     * Pseudo-extension Blizzard regroupant les raids du tier courant dans
     * la réponse encounters/raids. Stable d'un patch à l'autre : Blizzard
     * en met le contenu à jour, aucun identifiant à maintenir côté app.
     */
    public const CURRENT_SEASON_EXPANSION_ID = 505;

    private const DIFFICULTY_ORDER = [
        'LFR' => 0,
        'NORMAL' => 1,
        'HEROIC' => 2,
        'MYTHIC' => 3,
    ];

    private const DIFFICULTY_LABELS = [
        'LFR' => 'LFR',
        'NORMAL' => 'Normal',
        'HEROIC' => 'Héroïque',
        'MYTHIC' => 'Mythique',
    ];

    /**
     * @param  array<string, mixed>  $raidsResponse
     * @param  array<int, array{name?: string, encounters?: array<int, string>}>  $nameMap  Noms FR résolus via les données statiques journal-instance
     * @return list<array<string, mixed>>|null
     */
    public function aggregate(array $raidsResponse, array $nameMap = []): ?array
    {
        /** @var list<array<string, mixed>> $expansions */
        $expansions = $raidsResponse['expansions'] ?? [];

        $currentSeason = null;
        foreach ($expansions as $expansion) {
            /** @var array{id?: int} $expansionData */
            $expansionData = $expansion['expansion'] ?? [];
            if ((int) ($expansionData['id'] ?? 0) === self::CURRENT_SEASON_EXPANSION_ID) {
                $currentSeason = $expansion;
                break;
            }
        }

        if ($currentSeason === null) {
            return null;
        }

        /** @var list<array<string, mixed>> $instances */
        $instances = $currentSeason['instances'] ?? [];

        $raids = [];
        foreach ($instances as $instance) {
            $raids[] = $this->buildRaid($instance, $nameMap);
        }

        return $raids;
    }

    /**
     * @param  array<string, mixed>  $instance
     * @param  array<int, array{name?: string, encounters?: array<int, string>}>  $nameMap
     * @return array<string, mixed>
     */
    private function buildRaid(array $instance, array $nameMap): array
    {
        /** @var array{id?: int, name?: string} $instanceData */
        $instanceData = $instance['instance'] ?? [];
        /** @var list<array<string, mixed>> $modes */
        $modes = $instance['modes'] ?? [];

        $instanceId = (int) ($instanceData['id'] ?? 0);
        $localizedNames = $nameMap[$instanceId] ?? [];
        /** @var array<int, string> $encounterNames */
        $encounterNames = $localizedNames['encounters'] ?? [];

        $builtModes = array_map(fn (array $mode): array => $this->buildMode($mode, $encounterNames), $modes);

        usort(
            $builtModes,
            fn (array $a, array $b): int => $this->difficultyOrder($a) <=> $this->difficultyOrder($b),
        );

        return [
            'instance_id' => $instanceId,
            'instance_name' => $this->preferLocalized($localizedNames['name'] ?? null, $instanceData['name'] ?? null),
            'modes' => $builtModes,
        ];
    }

    /**
     * Privilégie le nom FR (données statiques) et retombe sur le nom brut de l'API profil.
     */
    private function preferLocalized(?string $localized, mixed $fallback): string
    {
        if ($localized !== null && $localized !== '') {
            return $localized;
        }

        return is_string($fallback) ? $fallback : '';
    }

    /**
     * @param  array<string, mixed>  $mode
     */
    private function difficultyOrder(array $mode): int
    {
        $type = $mode['difficulty_type'] ?? '';

        return is_string($type) ? (self::DIFFICULTY_ORDER[$type] ?? 99) : 99;
    }

    /**
     * @param  array<string, mixed>  $mode
     * @param  array<int, string>  $encounterNames
     * @return array<string, mixed>
     */
    private function buildMode(array $mode, array $encounterNames): array
    {
        /** @var array{type?: string} $difficulty */
        $difficulty = $mode['difficulty'] ?? [];
        $type = (string) ($difficulty['type'] ?? '');

        /** @var array{completed_count?: int, total_count?: int, encounters?: list<array<string, mixed>>} $progress */
        $progress = $mode['progress'] ?? [];
        /** @var list<array<string, mixed>> $encounters */
        $encounters = $progress['encounters'] ?? [];

        return [
            'difficulty_type' => $type,
            'difficulty_label' => self::DIFFICULTY_LABELS[$type] ?? $type,
            'completed_count' => (int) ($progress['completed_count'] ?? 0),
            'total_count' => (int) ($progress['total_count'] ?? 0),
            'encounters' => array_map(fn (array $encounter): array => $this->buildEncounter($encounter, $encounterNames), $encounters),
        ];
    }

    /**
     * @param  array<string, mixed>  $encounter
     * @param  array<int, string>  $encounterNames
     * @return array{id: int, name: string, last_kill_timestamp: int}
     */
    private function buildEncounter(array $encounter, array $encounterNames): array
    {
        /** @var array{id?: int, name?: string} $encounterData */
        $encounterData = $encounter['encounter'] ?? [];
        $lastKill = $encounter['last_kill_timestamp'] ?? 0;
        $encounterId = (int) ($encounterData['id'] ?? 0);

        return [
            'id' => $encounterId,
            'name' => $this->preferLocalized($encounterNames[$encounterId] ?? null, $encounterData['name'] ?? null),
            'last_kill_timestamp' => is_numeric($lastKill) ? (int) $lastKill : 0,
        ];
    }
}
