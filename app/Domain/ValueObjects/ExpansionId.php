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
            throw new InvalidArgumentException("Invalid Expansion ID: " . $this->value);
        }
    }

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
}
