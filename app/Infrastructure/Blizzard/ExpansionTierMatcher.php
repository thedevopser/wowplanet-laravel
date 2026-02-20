<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

class ExpansionTierMatcher
{
    /**
     * Match a skill tier name (French or English) to an expansion ID.
     * Most specific keywords first to avoid partial matches.
     */
    public static function match(string $name): ?int
    {
        $keywords = [
            // Multi-word (most specific first)
            "Royaumes de l'Est" => 0,
            'Battle for Azeroth' => 7,
            'Mists of Pandaria' => 4,
            'Burning Crusade' => 1,
            'Lich King' => 2,
            'War Within' => 10,
            'Kul Tir' => 7,
            'Zandalari' => 7,
            'Khaz Algar' => 10,
            'Dragon Isles' => 9,
            'les aux Dragons' => 9,

            // Single-word identifiers (French + English variants)
            'Kalimdor' => 0,
            'Classique' => 0,
            'Classic' => 0,
            'Outreterre' => 1,
            'Outland' => 1,
            'Norfendre' => 2,
            'Northrend' => 2,
            'Cataclysm' => 3,
            'Cataclysme' => 3,
            'Pandarie' => 4,
            'Pandaria' => 4,
            'Draenor' => 5,
            'Warlords' => 5,
            'Legion' => 6,
            'Légion' => 6,
            'Ombreterre' => 8,
            'Shadowlands' => 8,
            'Dragonflight' => 9,
            'Midnight' => 11,
        ];

        foreach ($keywords as $keyword => $expansionId) {
            if (mb_stripos($name, $keyword) !== false) {
                return $expansionId;
            }
        }

        return null;
    }
}
