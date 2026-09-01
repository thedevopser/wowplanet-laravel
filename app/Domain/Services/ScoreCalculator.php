<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\ValueObjects\CompletionScore;
use App\Domain\ValueObjects\ScoreDimension;
use App\Domain\ValueObjects\ScoreInput;
use App\Domain\ValueObjects\ScoreWeights;

/**
 * Calcule le score de complétion. Service pur, seule implémentation de la formule.
 *
 * Le global est une moyenne pondérée renormalisée sur les seules dimensions applicables :
 * une dimension sans données sort du calcul au lieu de valoir 0.
 */
class ScoreCalculator
{
    /** Valeur d'un boss selon son meilleur palier ; il ne compte qu'une fois. */
    private const RAID_DIFFICULTY_VALUES = [
        'LFR' => 0.25,
        'NORMAL' => 0.50,
        'HEROIC' => 0.75,
        'MYTHIC' => 1.00,
    ];

    public function compute(ScoreInput $scoreInput): CompletionScore
    {
        $stats = [
            'quests' => $this->sumCollections($scoreInput->collections, 'quests'),
            'achievements' => $this->sumCollections($scoreInput->collections, 'achievements'),
            'reputations' => $this->sumCollections($scoreInput->collections, 'reputations'),
            'raids' => $this->sumRaids($scoreInput->raids),
            'mounts' => $this->countItems($scoreInput->mounts),
            'transmog' => $this->sumAppearances($scoreInput->appearances),
            'pets' => $this->countItems($scoreInput->pets),
            'decor' => $this->countItems($scoreInput->decor),
            'professions' => $this->sumProfessions($scoreInput),
        ];

        $dimensions = [];
        $weightedSum = 0.0;
        $applicableWeight = 0.0;

        foreach (ScoreWeights::WEIGHTS as $key => $weight) {
            ['completed' => $completed, 'total' => $total] = $stats[$key];

            $applicable = $total > 0;
            $score = $applicable ? $completed / $total * 100 : 0.0;

            if ($applicable) {
                $weightedSum += $score * $weight;
                $applicableWeight += $weight;
            }

            $dimensions[] = new ScoreDimension(
                key: $key,
                label: ScoreWeights::LABELS[$key],
                weight: $weight,
                completed: $completed,
                total: $total,
                score: $score,
                applicable: $applicable,
            );
        }

        $global = $applicableWeight > 0.0 ? round($weightedSum / $applicableWeight, 1) : 0.0;

        return new CompletionScore(
            version: ScoreWeights::VERSION,
            global: $global,
            rank: $this->rank($global),
            dimensions: $dimensions,
        );
    }

    private function rank(float $global): string
    {
        return match (true) {
            $global >= 90 => 'Légendaire',
            $global >= 75 => 'Épique',
            $global >= 50 => 'Rare',
            $global >= 25 => 'Commun',
            default => 'Débutant',
        };
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $collections
     * @return array{completed: float, total: int}
     */
    private function sumCollections(array $collections, string $type): array
    {
        $completed = 0;
        $total = 0;

        foreach ($collections as $collection) {
            $stats = $collection[$type] ?? null;
            if (! is_array($stats)) {
                continue;
            }

            $completed += is_numeric($stats['completed'] ?? null) ? (int) $stats['completed'] : 0;
            $total += is_numeric($stats['total'] ?? null) ? (int) $stats['total'] : 0;
        }

        return ['completed' => (float) $completed, 'total' => $total];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{completed: float, total: int}
     */
    private function countItems(array $items): array
    {
        $completed = 0;

        foreach ($items as $item) {
            if (! empty($item['is_completed'])) {
                $completed++;
            }
        }

        return ['completed' => (float) $completed, 'total' => count($items)];
    }

    /**
     * @param  list<array{slot?: string, total?: int, completed?: int}>  $appearances
     * @return array{completed: float, total: int}
     */
    private function sumAppearances(array $appearances): array
    {
        $completed = 0;
        $total = 0;

        foreach ($appearances as $appearance) {
            $completed += is_numeric($appearance['completed'] ?? null) ? (int) $appearance['completed'] : 0;
            $total += is_numeric($appearance['total'] ?? null) ? (int) $appearance['total'] : 0;
        }

        return ['completed' => (float) $completed, 'total' => $total];
    }

    /**
     * `completed` est un équivalent-mythique : 8 boss tués en normal valent 4.
     *
     * @param  list<array<string, mixed>>|null  $raids
     * @return array{completed: float, total: int}
     */
    private function sumRaids(?array $raids): array
    {
        if ($raids === null) {
            return ['completed' => 0.0, 'total' => 0];
        }

        $points = 0.0;
        $total = 0;

        foreach ($raids as $raid) {
            /** @var list<array<string, mixed>> $modes */
            $modes = is_array($raid['modes'] ?? null) ? $raid['modes'] : [];

            /** @var array<int, float> $bestByBoss */
            $bestByBoss = [];
            $bossCount = 0;

            foreach ($modes as $mode) {
                $bossCount = max($bossCount, is_numeric($mode['total_count'] ?? null) ? (int) $mode['total_count'] : 0);

                $difficulty = is_string($mode['difficulty_type'] ?? null) ? $mode['difficulty_type'] : '';
                $value = self::RAID_DIFFICULTY_VALUES[$difficulty] ?? 0.0;
                if ($value === 0.0) {
                    continue;
                }

                /** @var list<array<string, mixed>> $encounters */
                $encounters = is_array($mode['encounters'] ?? null) ? $mode['encounters'] : [];
                foreach ($encounters as $encounter) {
                    if (! is_numeric($encounter['id'] ?? null)) {
                        continue;
                    }

                    $bossId = (int) $encounter['id'];
                    $bestByBoss[$bossId] = max($bestByBoss[$bossId] ?? 0.0, $value);
                }
            }

            $points += array_sum($bestByBoss);
            $total += $bossCount;
        }

        // Les paliers valent des quarts : deux décimales restent exactes.
        return ['completed' => round($points, 2), 'total' => $total];
    }

    /**
     * Le meilleur ratio métier du compte prime sur le cumul du personnage courant.
     *
     * @return array{completed: float, total: int}
     */
    private function sumProfessions(ScoreInput $scoreInput): array
    {
        $best = $scoreInput->bestProfessionStats;
        if ($best !== null && $best['total'] > 0) {
            return ['completed' => (float) $best['completed'], 'total' => $best['total']];
        }

        $recipeCompleted = 0;
        $recipeTotal = 0;
        $skillPoints = 0;
        $skillMax = 0;

        foreach ($scoreInput->professions as $profession) {
            /** @var array<int|string, array<string, mixed>> $expansions */
            $expansions = is_array($profession['expansions'] ?? null) ? $profession['expansions'] : [];

            foreach ($expansions as $expansion) {
                $recipeCompleted += is_numeric($expansion['completed'] ?? null) ? (int) $expansion['completed'] : 0;
                $recipeTotal += is_numeric($expansion['total'] ?? null) ? (int) $expansion['total'] : 0;
                $skillPoints += is_numeric($expansion['skill_points'] ?? null) ? (int) $expansion['skill_points'] : 0;
                $skillMax += is_numeric($expansion['max_skill_points'] ?? null) ? (int) $expansion['max_skill_points'] : 0;
            }
        }

        // Sans référentiel de recettes, les points de compétence sont la seule mesure.
        if ($recipeTotal > 0) {
            return ['completed' => (float) $recipeCompleted, 'total' => $recipeTotal];
        }

        return ['completed' => (float) $skillPoints, 'total' => $skillMax];
    }
}
