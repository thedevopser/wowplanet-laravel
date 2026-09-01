<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Domain\Services\PvpBracketClassifier;

/**
 * Normalise le PvP d'un personnage : résumé (`pvp-summary`) et détail par bracket
 * (`pvp-bracket/{slug}`), le tout regroupé par mode de jeu.
 *
 * Service pur : aucun appel API, aucune dépendance. L'orchestration des requêtes
 * appartient à PvpProfileService.
 *
 * @phpstan-type PvpBracket array{slug: string, group: string, label: string, spec: string|null,
 *     rating: int, season_id: int, tier_name: string|null, tier_icon_url: string|null,
 *     played: int, won: int, lost: int, win_rate: float,
 *     weekly: array{played: int, won: int, lost: int}}
 */
class PvpProgressAggregator
{
    /** Groupes dont les brackets se classent par rating décroissant (un par spécialisation). */
    private const RATING_SORTED_GROUPS = ['shuffle', 'blitz'];

    public function __construct(
        private readonly PvpBracketClassifier $pvpBracketClassifier = new PvpBracketClassifier,
    ) {}

    /**
     * @param  array<string, mixed>  $summary  Réponse pvp-summary
     * @param  array<string, array<string, mixed>>  $bracketResponses  Réponses pvp-bracket, indexées par slug
     * @param  array<int, array{name?: string, icon_url?: string}>  $tiers  Paliers résolus, indexés par id
     * @param  int  $currentSeasonId  0 si la saison courante n'a pas pu être résolue
     * @param  array<string, string>  $specNames  Spécialisations FR par slug, en repli si l'API n'en fournit pas
     * @return array<string, mixed>|null
     */
    public function aggregate(array $summary, array $bracketResponses, array $tiers, int $currentSeasonId, array $specNames = []): ?array
    {
        if ($summary === []) {
            return null;
        }

        $brackets = [];
        $seasonId = $currentSeasonId;
        $bestRating = 0;

        foreach ($bracketResponses as $slug => $response) {
            $bracket = $this->buildBracket((string) $slug, $response, $tiers, $currentSeasonId, $specNames[$slug] ?? null);

            if ($bracket === null) {
                continue;
            }

            if ($seasonId === 0) {
                $seasonId = $bracket['season_id'];
            }

            $bestRating = max($bestRating, $bracket['rating']);
            $brackets[] = $bracket;
        }

        $honorLevel = $this->intValue($summary, 'honor_level');
        $honorableKills = $this->intValue($summary, 'honorable_kills');
        $battlegrounds = $this->buildBattlegrounds($summary);

        if ($brackets === [] && $honorLevel === 0 && $honorableKills === 0 && $battlegrounds['played'] === 0) {
            return null;
        }

        return [
            'season_id' => $seasonId,
            'honor_level' => $honorLevel,
            'honorable_kills' => $honorableKills,
            'best_rating' => $bestRating,
            'battlegrounds' => $battlegrounds,
            'groups' => $this->buildGroups($brackets),
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<int, array{name?: string, icon_url?: string}>  $tiers
     * @return PvpBracket|null
     */
    private function buildBracket(string $slug, array $response, array $tiers, int $currentSeasonId, ?string $fallbackSpec = null): ?array
    {
        if ($response === []) {
            return null;
        }

        /** @var array{id?: int} $season */
        $season = $response['season'] ?? [];
        $seasonId = (int) ($season['id'] ?? 0);

        // Un bracket d'une saison antérieure porte un rating périmé : on l'écarte.
        // Saison courante inconnue (index API indisponible) → on n'écarte rien.
        if ($currentSeasonId > 0 && $seasonId !== $currentSeasonId) {
            return null;
        }

        $rating = $this->intValue($response, 'rating');
        /** @var array<string, mixed> $seasonStatistics */
        $seasonStatistics = is_array($response['season_match_statistics'] ?? null) ? $response['season_match_statistics'] : [];
        $statistics = $this->buildStatistics($seasonStatistics);

        // Ni rating ni match joué : rien à montrer.
        if ($rating === 0 && $statistics['played'] === 0) {
            return null;
        }

        /** @var array<string, mixed> $weeklyStatistics */
        $weeklyStatistics = is_array($response['weekly_match_statistics'] ?? null) ? $response['weekly_match_statistics'] : [];

        /** @var array{id?: int} $tier */
        $tier = is_array($response['tier'] ?? null) ? $response['tier'] : [];
        $tierId = (int) ($tier['id'] ?? 0);
        $tierData = $tiers[$tierId] ?? [];

        /** @var array{name?: string} $specialization */
        $specialization = is_array($response['specialization'] ?? null) ? $response['specialization'] : [];
        $spec = is_string($specialization['name'] ?? null) && $specialization['name'] !== ''
            ? $specialization['name']
            : $fallbackSpec;

        return [
            'slug' => $slug,
            'group' => $this->pvpBracketClassifier->groupFor($slug),
            'label' => $this->pvpBracketClassifier->labelFor($slug, $spec),
            'spec' => $spec,
            'rating' => $rating,
            'season_id' => $seasonId,
            'tier_name' => is_string($tierData['name'] ?? null) && $tierData['name'] !== '' ? $tierData['name'] : null,
            'tier_icon_url' => is_string($tierData['icon_url'] ?? null) && $tierData['icon_url'] !== '' ? $tierData['icon_url'] : null,
            'played' => $statistics['played'],
            'won' => $statistics['won'],
            'lost' => $statistics['lost'],
            'win_rate' => $statistics['win_rate'],
            'weekly' => [
                'played' => $this->intValue($weeklyStatistics, 'played'),
                'won' => $this->intValue($weeklyStatistics, 'won'),
                'lost' => $this->intValue($weeklyStatistics, 'lost'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $statistics
     * @return array{played: int, won: int, lost: int, win_rate: float}
     */
    private function buildStatistics(array $statistics): array
    {
        $played = $this->intValue($statistics, 'played');
        $won = $this->intValue($statistics, 'won');

        return [
            'played' => $played,
            'won' => $won,
            'lost' => $this->intValue($statistics, 'lost'),
            'win_rate' => $played > 0 ? round($won / $played * 100, 1) : 0.0,
        ];
    }

    /**
     * Cumule les statistiques de champs de bataille non cotés, seule progression
     * PvP visible pour les personnages qui ne jouent aucun mode coté.
     *
     * @param  array<string, mixed>  $summary
     * @return array{played: int, won: int, lost: int, win_rate: float}
     */
    private function buildBattlegrounds(array $summary): array
    {
        /** @var list<array<string, mixed>> $maps */
        $maps = is_array($summary['pvp_map_statistics'] ?? null) ? $summary['pvp_map_statistics'] : [];

        $totals = ['played' => 0, 'won' => 0, 'lost' => 0];
        foreach ($maps as $map) {
            /** @var array<string, mixed> $statistics */
            $statistics = is_array($map['match_statistics'] ?? null) ? $map['match_statistics'] : [];

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $this->intValue($statistics, $key);
            }
        }

        return $this->buildStatistics($totals);
    }

    /**
     * @param  list<PvpBracket>  $brackets
     * @return list<array{key: string, label: string, brackets: list<PvpBracket>}>
     */
    private function buildGroups(array $brackets): array
    {
        $groups = [];

        foreach (PvpBracketClassifier::GROUPS as $key => $label) {
            $groupBrackets = array_values(array_filter($brackets, fn (array $bracket): bool => $bracket['group'] === $key));

            if ($groupBrackets === []) {
                continue;
            }

            if (in_array($key, self::RATING_SORTED_GROUPS, true)) {
                usort($groupBrackets, fn (array $a, array $b): int => $b['rating'] <=> $a['rating']);
            } else {
                usort($groupBrackets, fn (array $a, array $b): int => strcmp($a['slug'], $b['slug']));
            }

            $groups[] = ['key' => $key, 'label' => $label, 'brackets' => $groupBrackets];
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function intValue(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }
}
