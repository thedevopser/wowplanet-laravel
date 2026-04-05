<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Models\WowAchievement;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;

class SeoContentRenderer
{
    public function renderHome(string $appUrl): string
    {
        $mountCount = WowMount::query()->where('is_active', true)->count();
        $achievementCount = WowAchievement::query()->where('is_active', true)->count();
        $questCount = WowQuest::query()->where('is_active', true)->count();
        $petCount = WowPet::query()->where('is_active', true)->count();
        $decorCount = WowDecor::query()->where('is_active', true)->count();
        $professionCount = WowProfession::query()->where('is_active', true)->count();

        $dbUrl = $appUrl.'/base-de-donnees';

        return $this->wrap(
            '<h1>WowPlanet — Suivi de progression World of Warcraft en français</h1>'
            .'<p>Analysez votre personnage World of Warcraft en français : quêtes, hauts-faits, montures, mascottes, décorations et professions.</p>'
            .'<h2>Base de données WoW</h2>'
            .'<nav aria-label="Collections"><ul>'
            .sprintf('<li><a href="%s/montures">Montures (%s)</a></li>', $dbUrl, number_format($mountCount, 0, ',', "\u{202f}"))
            .sprintf('<li><a href="%s/hauts-faits">Hauts-faits (%s)</a></li>', $dbUrl, number_format($achievementCount, 0, ',', "\u{202f}"))
            .sprintf('<li><a href="%s/quetes">Quêtes (%s)</a></li>', $dbUrl, number_format($questCount, 0, ',', "\u{202f}"))
            .sprintf('<li><a href="%s/mascottes">Mascottes (%s)</a></li>', $dbUrl, number_format($petCount, 0, ',', "\u{202f}"))
            .sprintf('<li><a href="%s/decorations">Décorations (%s)</a></li>', $dbUrl, number_format($decorCount, 0, ',', "\u{202f}"))
            .sprintf('<li><a href="%s/professions">Professions (%d)</a></li>', $dbUrl, $professionCount)
            .'</ul></nav>',
        );
    }

    /**
     * @param  array<string, string|int|bool>  $charData
     */
    public function renderCharacter(string $appUrl, array $charData, string $realm, string $name): string
    {
        if (empty($charData['found'])) {
            return $this->wrap(
                sprintf('<h1>%s — %s</h1>', e(ucfirst($name)), e(ucfirst($realm)))
                .'<p>Profil du personnage World of Warcraft sur WowPlanet.</p>',
            );
        }

        return $this->wrap(
            sprintf(
                '<h1>%s — %s %s niveau %s | %s</h1>',
                e((string) $charData['name']),
                e((string) ($charData['race'] ?? '')),
                e((string) ($charData['class'] ?? '')),
                e((string) ($charData['level'] ?? '')),
                e((string) $charData['realm']),
            )
            .sprintf(
                '<p>%s, %s %s niveau %s (ilvl %s) sur %s (%s). Consultez sa progression : quêtes, hauts-faits, montures et mascottes.</p>',
                e((string) $charData['name']),
                e((string) ($charData['race'] ?? '')),
                e((string) ($charData['class'] ?? '')),
                e((string) ($charData['level'] ?? '')),
                e((string) ($charData['ilvl'] ?? '')),
                e((string) $charData['realm']),
                e((string) ($charData['faction'] ?? '')),
            ),
        );
    }

    private function wrap(string $content): string
    {
        return $content;
    }
}
