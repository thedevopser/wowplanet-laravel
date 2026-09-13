<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\WowAchievement;
use App\Models\WowAppearance;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use Illuminate\Database\Eloquent\Model;

/**
 * Les étapes d'un import complet, dans l'ordre où elles s'enchaînent.
 *
 * Le socle de référence est une étape et non une entité de catalogue : les quêtes, les
 * montures et les métiers en tirent ce que l'API n'expose pas, et un import parti d'une
 * base vide sans lui sortirait faux sans rien signaler. Il ouvre donc la chaîne.
 *
 * L'ordre est vérifié contre les dépendances déclarées plutôt que simplement écrit :
 * une dépendance ajoutée à contresens de la chaîne fait tomber le test.
 */
enum ImportStage: string
{
    case Reference = 'reference';
    case Achievements = 'achievements';
    case Quests = 'quests';
    case Professions = 'professions';
    case Mounts = 'mounts';
    case Pets = 'pets';
    case Decor = 'decor';
    case Appearances = 'appearances';

    /**
     * @return list<self>
     */
    public static function chain(): array
    {
        return self::cases();
    }

    /**
     * Étapes demandées par `--type`, la chaîne entière pour `all`.
     *
     * Une étape nommée seule s'exécute seule : elle ne tire pas le socle derrière elle,
     * pour qu'un réimport ciblé reste ce que l'exploitant a demandé.
     *
     * @return list<self>
     */
    public static function requested(string $type): array
    {
        if ($type === 'all') {
            return self::chain();
        }

        $stage = self::tryFrom($type);

        return $stage instanceof self ? [$stage] : [];
    }

    /**
     * @return list<self>
     */
    public function dependsOn(): array
    {
        return match ($this) {
            self::Quests, self::Professions, self::Mounts => [self::Reference],
            default => [],
        };
    }

    /**
     * Tables dont les lignes créées, mises à jour et supprimées composent le rapport.
     *
     * Vide pour le socle, chargé par `COPY` dans des tables sans horodatages : son
     * étape ne rapporte donc aucun décompte de lignes, plutôt que trois zéros.
     *
     * @return list<class-string<Model>>
     */
    public function tables(): array
    {
        return match ($this) {
            self::Reference => [],
            self::Achievements => [WowAchievement::class],
            self::Quests => [WowQuest::class],
            self::Professions => [WowProfession::class, WowRecipe::class],
            self::Mounts => [WowMount::class],
            self::Pets => [WowPet::class],
            self::Decor => [WowDecor::class],
            self::Appearances => [WowAppearance::class],
        };
    }

    /**
     * Le socle se charge depuis wago : il ne touche pas au quota Blizzard, et n'a donc
     * pas à attendre qu'il se libère.
     */
    public function usesBlizzardApi(): bool
    {
        return $this !== self::Reference;
    }

    /**
     * Une étape reprenable rend la main avant d'avoir fini et repart d'un offset.
     */
    public function isResumable(): bool
    {
        return $this === self::Appearances;
    }

    public function label(): string
    {
        return match ($this) {
            self::Reference => 'Socle de référence',
            self::Achievements => 'Hauts faits',
            self::Quests => 'Quêtes',
            self::Professions => 'Métiers',
            self::Mounts => 'Montures',
            self::Pets => 'Mascottes',
            self::Decor => 'Décorations',
            self::Appearances => 'Garde-robe',
        };
    }
}
