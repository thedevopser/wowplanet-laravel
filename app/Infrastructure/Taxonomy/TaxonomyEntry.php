<?php

declare(strict_types=1);

namespace App\Infrastructure\Taxonomy;

/**
 * Rangement curé d'une entrée : sa catégorie de niveau 1 et sa source de niveau 2.
 *
 * Les deux libellés sont ceux de la curation, en anglais brut, traduits à l'affichage par les
 * onglets de collection. Les deux peuvent être nuls : c'est une entrée rangée nulle part en
 * connaissance de cause, à ne pas confondre avec l'absence d'entrée, qui est à arbitrer.
 */
final readonly class TaxonomyEntry
{
    public function __construct(
        public ?string $category,
        public ?string $source,
    ) {}
}
