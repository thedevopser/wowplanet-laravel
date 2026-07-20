<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use Illuminate\Support\Facades\Cache;

/**
 * Fenêtre glissante d'une heure sur le quota d'appels Blizzard (36 000 req/h).
 * Complète RateLimitingMiddleware (limite par seconde) pour les imports massifs :
 * l'importer interroge secondsUntilAvailable() avant chaque lot et attend si besoin.
 */
final class HourlyBudgetGuard
{
    /** Marge de sécurité sous le quota réel de 36 000 req/h. */
    public const HOURLY_LIMIT = 34000;

    private const CACHE_KEY = 'blizzard_hourly_budget';

    private const WINDOW_S = 3600;

    public function consume(int $count): void
    {
        $buckets = $this->buckets();
        $minute = $this->currentMinute();
        $buckets[$minute] = ($buckets[$minute] ?? 0) + $count;

        Cache::put(self::CACHE_KEY, $buckets, self::WINDOW_S + 60);
    }

    /**
     * Secondes à attendre avant de pouvoir consommer $count requêtes sans dépasser
     * le plafond ($ceiling), ou HOURLY_LIMIT si non fourni. Les imports passent un
     * plafond réservé (< HOURLY_LIMIT) pour laisser de la marge au trafic du site.
     */
    public function secondsUntilAvailable(int $count, ?int $ceiling = null): int
    {
        $limit = $ceiling ?? self::HOURLY_LIMIT;

        $buckets = $this->buckets();
        $used = array_sum($buckets);

        if ($buckets === [] || $used + $count <= $limit) {
            return 0;
        }

        // Attendre que le plus vieux bucket sorte de la fenêtre glissante
        $oldestMinute = min(array_keys($buckets));

        return max(1, ($oldestMinute * 60 + self::WINDOW_S + 60) - now()->getTimestamp());
    }

    /**
     * Buckets par minute (epoch/60 => nb requêtes), purgés de tout ce qui a plus d'une heure.
     *
     * @return array<int, int>
     */
    private function buckets(): array
    {
        /** @var array<int, int> $buckets */
        $buckets = Cache::get(self::CACHE_KEY, []);
        $cutoff = $this->currentMinute() - 60;

        return array_filter($buckets, fn (int $minute): bool => $minute > $cutoff, ARRAY_FILTER_USE_KEY);
    }

    private function currentMinute(): int
    {
        return intdiv(now()->getTimestamp(), 60);
    }
}
