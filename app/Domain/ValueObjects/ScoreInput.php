<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

/**
 * Entrée du calcul, commune au profil d'un personnage et au profil virtuel d'un compte.
 */
final readonly class ScoreInput
{
    /**
     * @param  array<int|string, array<string, mixed>>  $collections  Par extension : quests / achievements / reputations
     * @param  list<array<string, mixed>>  $mounts  Items portant `is_completed`
     * @param  list<array<string, mixed>>  $pets
     * @param  list<array<string, mixed>>  $decor
     * @param  list<array<string, mixed>>  $professions
     * @param  list<array{slot?: string, total?: int, completed?: int}>  $appearances
     * @param  list<array<string, mixed>>|null  $raids  Tier courant, cf. RaidProgressAggregator
     * @param  array{completed: int, total: int}|null  $bestProfessionStats  Meilleur ratio métier du compte
     */
    public function __construct(
        public array $collections = [],
        public array $mounts = [],
        public array $pets = [],
        public array $decor = [],
        public array $professions = [],
        public array $appearances = [],
        public ?array $raids = null,
        public ?array $bestProfessionStats = null,
    ) {}
}
