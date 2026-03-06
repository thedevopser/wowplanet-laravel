<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

readonly class ExpansionId
{
    public const CLASSIC = 0;

    public const BURNING_CRUSADE = 1;

    public const WRATH_OF_THE_LICH_KING = 2;

    public const CATACLYSM = 3;

    public const MISTS_OF_PANDARIA = 4;

    public const WARLORDS_OF_DRAENOR = 5;

    public const LEGION = 6;

    public const BATTLE_FOR_AZEROTH = 7;

    public const SHADOWLANDS = 8;

    public const DRAGONFLIGHT = 9;

    public const THE_WAR_WITHIN = 10;

    public const MIDNIGHT = 11;

    public function __construct(public int $value)
    {
        if ($this->value < self::CLASSIC || $this->value > self::MIDNIGHT) {
            throw new InvalidArgumentException('Invalid Expansion ID: '.$this->value);
        }
    }

    private const SLUG_MAP = [
        self::CLASSIC => 'classic',
        self::BURNING_CRUSADE => 'burning-crusade',
        self::WRATH_OF_THE_LICH_KING => 'wrath',
        self::CATACLYSM => 'cataclysm',
        self::MISTS_OF_PANDARIA => 'pandaria',
        self::WARLORDS_OF_DRAENOR => 'draenor',
        self::LEGION => 'legion',
        self::BATTLE_FOR_AZEROTH => 'battle-for-azeroth',
        self::SHADOWLANDS => 'shadowlands',
        self::DRAGONFLIGHT => 'dragonflight',
        self::THE_WAR_WITHIN => 'the-war-within',
        self::MIDNIGHT => 'midnight',
    ];

    public function toString(): string
    {
        return match ($this->value) {
            self::CLASSIC => 'Classic',
            self::BURNING_CRUSADE => 'The Burning Crusade',
            self::WRATH_OF_THE_LICH_KING => 'Wrath of the Lich King',
            self::CATACLYSM => 'Cataclysm',
            self::MISTS_OF_PANDARIA => 'Mists of Pandaria',
            self::WARLORDS_OF_DRAENOR => 'Warlords of Draenor',
            self::LEGION => 'Legion',
            self::BATTLE_FOR_AZEROTH => 'Battle for Azeroth',
            self::SHADOWLANDS => 'Shadowlands',
            self::DRAGONFLIGHT => 'Dragonflight',
            self::THE_WAR_WITHIN => 'The War Within',
            self::MIDNIGHT => 'Midnight',
        };
    }

    public function toSlug(): string
    {
        return self::SLUG_MAP[$this->value];
    }

    public static function fromSlug(string $slug): ?self
    {
        $id = array_search($slug, self::SLUG_MAP, true);

        return $id !== false ? new self($id) : null;
    }

    /**
     * @return array<int, string>
     */
    public static function allSlugs(): array
    {
        return self::SLUG_MAP;
    }
}
