<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * État d'avancement d'une passe d'import d'apparences (importChunk).
 *
 * - $done : plus rien à traiter (toutes les tranches faites).
 * - $offset : index de reprise sur la liste d'IDs triée (porté d'une passe à l'autre).
 * - $total : nombre total d'apparences à traiter.
 * - $secondsUntilBudget : secondes à attendre avant la prochaine passe (0 si budget dispo).
 */
final readonly class AppearanceImportProgress
{
    public function __construct(
        public bool $done,
        public int $offset,
        public int $total,
        public int $secondsUntilBudget,
    ) {}
}
