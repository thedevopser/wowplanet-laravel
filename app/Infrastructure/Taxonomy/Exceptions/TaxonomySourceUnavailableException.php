<?php

declare(strict_types=1);

namespace App\Infrastructure\Taxonomy\Exceptions;

use RuntimeException;

/**
 * Le fichier curé dont la taxonomie s'amorce est absent, illisible ou vide.
 *
 * Levée plutôt que tolérée : un amorçage qui ne trouve rien et se tait laisserait croire
 * que la taxonomie est à jour alors qu'elle est restée vide.
 */
final class TaxonomySourceUnavailableException extends RuntimeException
{
    public static function for(string $filename): self
    {
        return new self(sprintf(
            'Aucune entrée curée lisible dans %s : fichier absent, illisible ou vide.',
            $filename,
        ));
    }
}
