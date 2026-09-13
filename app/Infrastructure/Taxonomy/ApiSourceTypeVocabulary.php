<?php

declare(strict_types=1);

namespace App\Infrastructure\Taxonomy;

/**
 * Conversion du vocabulaire de source de l'API vers celui de la taxonomie.
 *
 * L'API ne connaît que onze types de source là où la taxonomie curée en compte 170
 * pour les seules montures : la conversion est donc une valeur d'attente pour une
 * entrée que personne n'a encore rangée, jamais un remplacement d'une source curée.
 *
 * Les onze libellés produits existent déjà dans les dictionnaires de traduction des
 * trois onglets de collection. N'en ajouter un douzième qu'en l'y ajoutant aussi,
 * sans quoi il s'afficherait en anglais.
 */
final class ApiSourceTypeVocabulary
{
    /**
     * @var array<string, string>
     */
    private const PENDING_SOURCES = [
        'VENDOR' => 'Vendor',
        'DROP' => 'Drop',
        'ACHIEVEMENT' => 'Achievement',
        'QUEST' => 'Quest',
        'PROMOTION' => 'Promotion',
        'PROFESSION' => 'Profession',
        'PETSTORE' => 'Blizzard Store',
        'TCG' => 'Trading Card Game / Auction House',
        'TRADINGPOST' => 'Trading Post',
        'WORLDEVENT' => 'World Events',
        'WILDPET' => 'Wild Pet',
    ];

    public static function toPendingSource(?string $sourceType): ?string
    {
        if ($sourceType === null) {
            return null;
        }

        return self::PENDING_SOURCES[mb_strtoupper(trim($sourceType))] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function pendingSources(): array
    {
        return array_values(self::PENDING_SOURCES);
    }
}
