<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\ValueObjects\ExpansionId;
use App\Models\WowAchievement;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;

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

    public function renderDatabaseIndex(string $appUrl): string
    {
        $mountCount = WowMount::query()->where('is_active', true)->count();
        $achievementCount = WowAchievement::query()->where('is_active', true)->count();
        $questCount = WowQuest::query()->where('is_active', true)->count();
        $petCount = WowPet::query()->where('is_active', true)->count();
        $decorCount = WowDecor::query()->where('is_active', true)->count();
        $professionCount = WowProfession::query()->where('is_active', true)->count();
        $recipeCount = WowRecipe::query()->where('is_active', true)->count();

        $dbUrl = $appUrl.'/base-de-donnees';

        return $this->wrap(
            '<h1>Base de données World of Warcraft en français</h1>'
            .sprintf(
                '<p>Explorez la base de données complète de WoW : %s montures, %s hauts-faits, %s quêtes, %s mascottes, %s décorations et %d professions (%s recettes).</p>',
                number_format($mountCount, 0, ',', "\u{202f}"),
                number_format($achievementCount, 0, ',', "\u{202f}"),
                number_format($questCount, 0, ',', "\u{202f}"),
                number_format($petCount, 0, ',', "\u{202f}"),
                number_format($decorCount, 0, ',', "\u{202f}"),
                $professionCount,
                number_format($recipeCount, 0, ',', "\u{202f}"),
            )
            .$this->breadcrumb($appUrl, [['Base de données', $dbUrl]])
            .'<nav aria-label="Collections"><ul>'
            .sprintf('<li><a href="%s/montures">Montures — %s montures disponibles</a></li>', $dbUrl, number_format($mountCount, 0, ',', "\u{202f}"))
            .sprintf('<li><a href="%s/hauts-faits">Hauts-faits — %s hauts-faits disponibles</a></li>', $dbUrl, number_format($achievementCount, 0, ',', "\u{202f}"))
            .sprintf('<li><a href="%s/quetes">Quêtes — %s quêtes disponibles</a></li>', $dbUrl, number_format($questCount, 0, ',', "\u{202f}"))
            .sprintf('<li><a href="%s/mascottes">Mascottes — %s mascottes disponibles</a></li>', $dbUrl, number_format($petCount, 0, ',', "\u{202f}"))
            .sprintf('<li><a href="%s/decorations">Décorations — %s décorations disponibles</a></li>', $dbUrl, number_format($decorCount, 0, ',', "\u{202f}"))
            .sprintf('<li><a href="%s/professions">Professions — %d professions et %s recettes</a></li>', $dbUrl, $professionCount, number_format($recipeCount, 0, ',', "\u{202f}"))
            .'</ul></nav>',
        );
    }

    public function renderMounts(string $appUrl, ?string $categorySlug): string
    {
        $dbUrl = $appUrl.'/base-de-donnees';

        if ($categorySlug !== null) {
            $category = $this->findCategory($categorySlug, 'mounts');

            if ($category === null) {
                return $this->renderMounts($appUrl, null);
            }

            /** @var list<string> $items */
            $items = WowMount::query()->where('is_active', true)
                ->where('category', $category)
                ->pluck('name_fr')
                ->all();

            return $this->wrap(
                sprintf('<h1>Montures WoW %s — %d montures</h1>', e($category), count($items))
                .$this->breadcrumb($appUrl, [
                    ['Base de données', $dbUrl],
                    ['Montures', $dbUrl.'/montures'],
                    [$category, $dbUrl.'/montures/'.$categorySlug],
                ])
                .$this->itemList($items),
            );
        }

        /** @var array<string, int> $categories */
        $categories = WowMount::query()->where('is_active', true)
            ->whereNotNull('category')
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->all();

        $count = WowMount::query()->where('is_active', true)->count();

        return $this->wrap(
            sprintf('<h1>Montures WoW — Liste complète des %s montures en français</h1>', number_format($count, 0, ',', "\u{202f}"))
            .$this->breadcrumb($appUrl, [
                ['Base de données', $dbUrl],
                ['Montures', $dbUrl.'/montures'],
            ])
            .'<p>Toutes les montures de World of Warcraft en français, triées par catégorie.</p>'
            .$this->categoryLinks($dbUrl.'/montures', $categories),
        );
    }

    public function renderAchievements(string $appUrl, ?string $expansionSlug): string
    {
        $dbUrl = $appUrl.'/base-de-donnees';

        if ($expansionSlug !== null) {
            $expansion = ExpansionId::fromSlug($expansionSlug);

            if (! $expansion instanceof ExpansionId) {
                return $this->renderAchievements($appUrl, null);
            }

            /** @var list<string> $items */
            $items = WowAchievement::query()->where('is_active', true)
                ->where('expansion_id', $expansion->value)
                ->pluck('name_fr')
                ->all();

            return $this->wrap(
                sprintf('<h1>Hauts-faits WoW %s — %d hauts-faits</h1>', e($expansion->toString()), count($items))
                .$this->breadcrumb($appUrl, [
                    ['Base de données', $dbUrl],
                    ['Hauts-faits', $dbUrl.'/hauts-faits'],
                    [$expansion->toString(), $dbUrl.'/hauts-faits/'.$expansionSlug],
                ])
                .$this->itemList($items),
            );
        }

        $expansionCounts = $this->expansionCounts(WowAchievement::class);
        $count = WowAchievement::query()->where('is_active', true)->count();

        return $this->wrap(
            sprintf('<h1>Hauts-faits WoW — %s hauts-faits en français</h1>', number_format($count, 0, ',', "\u{202f}"))
            .$this->breadcrumb($appUrl, [
                ['Base de données', $dbUrl],
                ['Hauts-faits', $dbUrl.'/hauts-faits'],
            ])
            .'<p>Tous les hauts-faits de World of Warcraft en français, classés par extension.</p>'
            .$this->expansionLinks($dbUrl.'/hauts-faits', $expansionCounts),
        );
    }

    public function renderQuests(string $appUrl, ?string $expansionSlug, ?string $zoneSlug): string
    {
        $dbUrl = $appUrl.'/base-de-donnees';

        if ($expansionSlug !== null) {
            $expansion = ExpansionId::fromSlug($expansionSlug);

            if (! $expansion instanceof ExpansionId) {
                return $this->renderQuests($appUrl, null, null);
            }

            if ($zoneSlug !== null) {
                $zoneName = $this->findCategory($zoneSlug, 'quest-zones', $expansion->value);

                if ($zoneName === null) {
                    return $this->renderQuests($appUrl, $expansionSlug, null);
                }

                /** @var list<string> $items */
                $items = WowQuest::query()->where('is_active', true)
                    ->where('expansion_id', $expansion->value)
                    ->where('zone_name', $zoneName)
                    ->pluck('name_fr')
                    ->all();

                return $this->wrap(
                    sprintf('<h1>Quêtes WoW %s (%s) — %d quêtes</h1>', e($zoneName), e($expansion->toString()), count($items))
                    .$this->breadcrumb($appUrl, [
                        ['Base de données', $dbUrl],
                        ['Quêtes', $dbUrl.'/quetes'],
                        [$expansion->toString(), $dbUrl.'/quetes/'.$expansionSlug],
                        [$zoneName, $dbUrl.'/quetes/'.$expansionSlug.'/'.$zoneSlug],
                    ])
                    .$this->itemList($items),
                );
            }

            /** @var array<string, int> $zones */
            $zones = WowQuest::query()->where('is_active', true)
                ->where('expansion_id', $expansion->value)
                ->whereNotNull('zone_name')
                ->selectRaw('zone_name, count(*) as total')
                ->groupBy('zone_name')
                ->pluck('total', 'zone_name')
                ->all();

            $count = WowQuest::query()->where('is_active', true)
                ->where('expansion_id', $expansion->value)
                ->count();

            return $this->wrap(
                sprintf('<h1>Quêtes WoW %s — %d quêtes</h1>', e($expansion->toString()), $count)
                .$this->breadcrumb($appUrl, [
                    ['Base de données', $dbUrl],
                    ['Quêtes', $dbUrl.'/quetes'],
                    [$expansion->toString(), $dbUrl.'/quetes/'.$expansionSlug],
                ])
                .sprintf('<p>Toutes les quêtes de %s dans World of Warcraft, triées par zone.</p>', e($expansion->toString()))
                .$this->categoryLinks($dbUrl.'/quetes/'.$expansionSlug, $zones),
            );
        }

        $expansionCounts = $this->expansionCounts(WowQuest::class);
        $count = WowQuest::query()->where('is_active', true)->count();

        return $this->wrap(
            sprintf('<h1>Quêtes WoW — %s quêtes en français</h1>', number_format($count, 0, ',', "\u{202f}"))
            .$this->breadcrumb($appUrl, [
                ['Base de données', $dbUrl],
                ['Quêtes', $dbUrl.'/quetes'],
            ])
            .'<p>Toutes les quêtes de World of Warcraft en français, triées par extension et zone.</p>'
            .$this->expansionLinks($dbUrl.'/quetes', $expansionCounts),
        );
    }

    public function renderPets(string $appUrl, ?string $categorySlug): string
    {
        $dbUrl = $appUrl.'/base-de-donnees';

        if ($categorySlug !== null) {
            $category = $this->findCategory($categorySlug, 'pets');

            if ($category === null) {
                return $this->renderPets($appUrl, null);
            }

            /** @var list<string> $items */
            $items = WowPet::query()->where('is_active', true)
                ->where('category', $category)
                ->pluck('name_fr')
                ->all();

            return $this->wrap(
                sprintf('<h1>Mascottes WoW %s — %d mascottes</h1>', e($category), count($items))
                .$this->breadcrumb($appUrl, [
                    ['Base de données', $dbUrl],
                    ['Mascottes', $dbUrl.'/mascottes'],
                    [$category, $dbUrl.'/mascottes/'.$categorySlug],
                ])
                .$this->itemList($items),
            );
        }

        /** @var array<string, int> $categories */
        $categories = WowPet::query()->where('is_active', true)
            ->whereNotNull('category')
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->all();

        $count = WowPet::query()->where('is_active', true)->count();

        return $this->wrap(
            sprintf('<h1>Mascottes WoW — Liste complète des %s mascottes en français</h1>', number_format($count, 0, ',', "\u{202f}"))
            .$this->breadcrumb($appUrl, [
                ['Base de données', $dbUrl],
                ['Mascottes', $dbUrl.'/mascottes'],
            ])
            .'<p>Toutes les mascottes de combat de World of Warcraft en français, triées par catégorie.</p>'
            .$this->categoryLinks($dbUrl.'/mascottes', $categories),
        );
    }

    public function renderDecors(string $appUrl, ?string $categorySlug): string
    {
        $dbUrl = $appUrl.'/base-de-donnees';

        if ($categorySlug !== null) {
            $category = $this->findCategory($categorySlug, 'decors');

            if ($category === null) {
                return $this->renderDecors($appUrl, null);
            }

            /** @var list<string> $items */
            $items = WowDecor::query()->where('is_active', true)
                ->where('category', $category)
                ->pluck('name_fr')
                ->all();

            return $this->wrap(
                sprintf('<h1>Décorations WoW %s — %d décorations</h1>', e($category), count($items))
                .$this->breadcrumb($appUrl, [
                    ['Base de données', $dbUrl],
                    ['Décorations', $dbUrl.'/decorations'],
                    [$category, $dbUrl.'/decorations/'.$categorySlug],
                ])
                .$this->itemList($items),
            );
        }

        /** @var array<string, int> $categories */
        $categories = WowDecor::query()->where('is_active', true)
            ->whereNotNull('category')
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->all();

        $count = WowDecor::query()->where('is_active', true)->count();

        return $this->wrap(
            sprintf('<h1>Décorations WoW — Liste complète des %s décorations en français</h1>', number_format($count, 0, ',', "\u{202f}"))
            .$this->breadcrumb($appUrl, [
                ['Base de données', $dbUrl],
                ['Décorations', $dbUrl.'/decorations'],
            ])
            .'<p>Toutes les décorations de World of Warcraft en français, triées par catégorie.</p>'
            .$this->categoryLinks($dbUrl.'/decorations', $categories),
        );
    }

    public function renderProfessions(string $appUrl, ?string $professionSlug): string
    {
        $dbUrl = $appUrl.'/base-de-donnees';

        if ($professionSlug !== null) {
            $profession = WowProfession::query()->where('is_active', true)
                ->get()
                ->first(fn (WowProfession $wowProfession): bool => $this->slugify($wowProfession->name_fr) === $professionSlug);

            if (! $profession instanceof WowProfession) {
                return $this->renderProfessions($appUrl, null);
            }

            $recipeCount = WowRecipe::query()->where('is_active', true)
                ->where('profession_id', $profession->id)
                ->count();

            /** @var list<string> $items */
            $items = WowRecipe::query()->where('is_active', true)
                ->where('profession_id', $profession->id)
                ->limit(100)
                ->pluck('name_fr')
                ->all();

            return $this->wrap(
                sprintf('<h1>%s WoW — %d recettes en français</h1>', e($profession->name_fr), $recipeCount)
                .$this->breadcrumb($appUrl, [
                    ['Base de données', $dbUrl],
                    ['Professions', $dbUrl.'/professions'],
                    [$profession->name_fr, $dbUrl.'/professions/'.$professionSlug],
                ])
                .$this->itemList($items),
            );
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, WowProfession> $professions */
        $professions = WowProfession::query()->where('is_active', true)->get();
        $recipeCount = WowRecipe::query()->where('is_active', true)->count();

        $links = '<ul>';
        foreach ($professions as $profession) {
            $profRecipeCount = WowRecipe::query()->where('is_active', true)
                ->where('profession_id', $profession->id)
                ->count();
            $links .= sprintf(
                '<li><a href="%s/professions/%s">%s (%d recettes)</a></li>',
                $dbUrl,
                $this->slugify($profession->name_fr),
                e($profession->name_fr),
                $profRecipeCount,
            );
        }

        $links .= '</ul>';

        return $this->wrap(
            sprintf('<h1>Professions WoW — %d professions et %s recettes en français</h1>', $professions->count(), number_format($recipeCount, 0, ',', "\u{202f}"))
            .$this->breadcrumb($appUrl, [
                ['Base de données', $dbUrl],
                ['Professions', $dbUrl.'/professions'],
            ])
            .'<p>Toutes les professions de World of Warcraft en français avec leurs recettes.</p>'
            .$links,
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

    /**
     * @param  list<array{0: string, 1: string}>  $items
     */
    private function breadcrumb(string $appUrl, array $items): string
    {
        $html = '<nav aria-label="Fil d\'Ariane"><ol>';
        $html .= sprintf('<li><a href="%s">WowPlanet</a></li>', $appUrl);
        foreach ($items as $item) {
            $html .= sprintf('<li><a href="%s">%s</a></li>', $item[1], e($item[0]));
        }

        return $html.'</ol></nav>';
    }

    /**
     * @param  array<string, int>  $categories
     */
    private function categoryLinks(string $baseUrl, array $categories): string
    {
        if ($categories === []) {
            return '';
        }

        $html = '<h2>Catégories</h2><ul>';
        foreach ($categories as $name => $count) {
            $html .= sprintf(
                '<li><a href="%s/%s">%s (%d)</a></li>',
                $baseUrl,
                $this->slugify($name),
                e($name),
                $count,
            );
        }

        return $html.'</ul>';
    }

    /**
     * @param  array<int, int>  $expansionCounts
     */
    private function expansionLinks(string $baseUrl, array $expansionCounts): string
    {
        if ($expansionCounts === []) {
            return '';
        }

        $html = '<h2>Extensions</h2><ul>';
        foreach ($expansionCounts as $id => $count) {
            $expansion = new ExpansionId($id);
            $html .= sprintf(
                '<li><a href="%s/%s">%s (%d)</a></li>',
                $baseUrl,
                $expansion->toSlug(),
                e($expansion->toString()),
                $count,
            );
        }

        return $html.'</ul>';
    }

    /**
     * @param  list<string>  $items
     */
    private function itemList(array $items, int $limit = 100): string
    {
        if ($items === []) {
            return '';
        }

        $displayed = array_slice($items, 0, $limit);
        $html = '<ul>';
        foreach ($displayed as $item) {
            $html .= sprintf('<li>%s</li>', e($item));
        }

        $html .= '</ul>';

        if (count($items) > $limit) {
            $html .= sprintf('<p>Et %d autres…</p>', count($items) - $limit);
        }

        return $html;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @return array<int, int>
     */
    private function expansionCounts(string $modelClass): array
    {
        /** @var array<int, int> $counts */
        $counts = [];

        foreach (array_keys(ExpansionId::allSlugs()) as $id) {
            $count = $modelClass::query()->where('is_active', true)
                ->where('expansion_id', $id)
                ->count();

            if ($count > 0) {
                $counts[$id] = $count;
            }
        }

        return $counts;
    }

    private function findCategory(string $slug, string $context, ?int $expansionId = null): ?string
    {
        /** @var list<string> $candidates */
        $candidates = match ($context) {
            'mounts' => WowMount::query()->where('is_active', true)
                ->whereNotNull('category')->distinct()->pluck('category')->all(),
            'pets' => WowPet::query()->where('is_active', true)
                ->whereNotNull('category')->distinct()->pluck('category')->all(),
            'decors' => WowDecor::query()->where('is_active', true)
                ->whereNotNull('category')->distinct()->pluck('category')->all(),
            'quest-zones' => WowQuest::query()->where('is_active', true)
                ->when($expansionId !== null, fn ($q) => $q->where('expansion_id', $expansionId))
                ->whereNotNull('zone_name')->distinct()->pluck('zone_name')->all(),
            default => [],
        };

        foreach ($candidates as $candidate) {
            if ($this->slugify($candidate) === $slug) {
                return $candidate;
            }
        }

        return null;
    }

    private function slugify(string $text): string
    {
        $slug = mb_strtolower($text);
        $slug = str_replace(
            [' ', "'", "\u{2019}", ':', '(', ')', ',', '.'],
            ['-', '-', '-', '', '', '', '', ''],
            $slug,
        );
        $slug = (string) preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = (string) preg_replace('/-+/', '-', $slug);

        return trim($slug, '-');
    }
}
