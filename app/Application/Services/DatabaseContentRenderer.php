<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\ValueObjects\ExpansionId;
use App\Models\WowAchievement;
use App\Models\WowAppearance;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;

class DatabaseContentRenderer
{
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

    public function renderMounts(string $appUrl, ?string $categorySlug): ?string
    {
        $dbUrl = $appUrl.'/base-de-donnees';

        if ($categorySlug !== null) {
            $category = $this->findCategory($categorySlug, 'mounts');

            if ($category === null) {
                return null;
            }

            /** @var list<array{name: string, url: string}> $items */
            $items = WowMount::query()->where('is_active', true)
                ->where('category', $category)
                ->select(['name_fr', 'source_spell_id'])
                ->get()
                ->map(fn (WowMount $wowMount): array => [
                    'name' => $wowMount->name_fr,
                    'url' => $wowMount->source_spell_id
                        ? 'https://www.wowhead.com/fr/spell='.$wowMount->source_spell_id
                        : 'https://www.wowhead.com/fr/search?q='.urlencode($wowMount->name_fr),
                ])
                ->all();

            /** @var array<string, int> $sourceCounts */
            $sourceCounts = WowMount::query()->where('is_active', true)
                ->where('category', $category)
                ->whereNotNull('source')
                ->selectRaw('source, count(*) as total')
                ->groupBy('source')
                ->pluck('total', 'source')
                ->all();

            $sourceText = $this->formatSourceSummary($sourceCounts);

            return $this->wrap(
                sprintf('<h1>Montures WoW %s — %d montures</h1>', e($category), count($items))
                .$this->breadcrumb($appUrl, [
                    ['Base de données', $dbUrl],
                    ['Montures', $dbUrl.'/montures'],
                    [$category, $dbUrl.'/montures/'.$categorySlug],
                ])
                .sprintf('<p>Retrouvez les %d montures %s de World of Warcraft en français.%s</p>', count($items), e($category), $sourceText)
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
            .sprintf('<p>Retrouvez toutes les montures de World of Warcraft en français, classées en %d catégories. Chaque monture est liée à sa fiche Wowhead.</p>', count($categories))
            .$this->categoryLinks($dbUrl.'/montures', $categories),
        );
    }

    public function renderAchievements(string $appUrl, ?string $expansionSlug): ?string
    {
        $dbUrl = $appUrl.'/base-de-donnees';

        if ($expansionSlug !== null) {
            $expansion = ExpansionId::fromSlug($expansionSlug);

            if (! $expansion instanceof ExpansionId) {
                return null;
            }

            /** @var list<array{name: string, url: string}> $items */
            $items = WowAchievement::query()->where('is_active', true)
                ->where('expansion_id', $expansion->value)
                ->select(['id', 'name_fr'])
                ->get()
                ->map(fn (WowAchievement $wowAchievement): array => [
                    'name' => $wowAchievement->name_fr,
                    'url' => 'https://www.wowhead.com/fr/achievement='.$wowAchievement->id,
                ])
                ->all();

            $totalPoints = (int) WowAchievement::query()->where('is_active', true)
                ->where('expansion_id', $expansion->value)
                ->sum('points');

            return $this->wrap(
                sprintf('<h1>Hauts-faits WoW %s — %d hauts-faits</h1>', e($expansion->toString()), count($items))
                .$this->breadcrumb($appUrl, [
                    ['Base de données', $dbUrl],
                    ['Hauts-faits', $dbUrl.'/hauts-faits'],
                    [$expansion->toString(), $dbUrl.'/hauts-faits/'.$expansionSlug],
                ])
                .sprintf(
                    '<p>%s est %s de World of Warcraft. Elle contient %d hauts-faits pour un total de %s points.</p>',
                    e($expansion->toString()),
                    $expansion->toOrdinal(),
                    count($items),
                    number_format($totalPoints, 0, ',', "\u{202f}"),
                )
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

    public function renderQuests(string $appUrl, ?string $expansionSlug): ?string
    {
        $dbUrl = $appUrl.'/base-de-donnees';

        if ($expansionSlug !== null) {
            $expansion = ExpansionId::fromSlug($expansionSlug);

            if (! $expansion instanceof ExpansionId) {
                return null;
            }

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
                .sprintf(
                    '<p>%s est %s de World of Warcraft. Elle contient %d quêtes.</p>',
                    e($expansion->toString()),
                    $expansion->toOrdinal(),
                    $count,
                ),
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
            .'<p>Toutes les quêtes de World of Warcraft en français, triées par extension.</p>'
            .$this->expansionLinks($dbUrl.'/quetes', $expansionCounts),
        );
    }

    public function renderPets(string $appUrl, ?string $categorySlug): ?string
    {
        $dbUrl = $appUrl.'/base-de-donnees';

        if ($categorySlug !== null) {
            $category = $this->findCategory($categorySlug, 'pets');

            if ($category === null) {
                return null;
            }

            /** @var list<array{name: string, url: string}> $items */
            $items = WowPet::query()->where('is_active', true)
                ->where('category', $category)
                ->select(['name_fr', 'creature_id'])
                ->get()
                ->map(fn (WowPet $wowPet): array => [
                    'name' => $wowPet->name_fr,
                    'url' => $wowPet->creature_id
                        ? 'https://www.wowhead.com/fr/npc='.$wowPet->creature_id
                        : 'https://www.wowhead.com/fr/search?q='.urlencode($wowPet->name_fr),
                ])
                ->all();

            /** @var array<string, int> $sourceCounts */
            $sourceCounts = WowPet::query()->where('is_active', true)
                ->where('category', $category)
                ->whereNotNull('source')
                ->selectRaw('source, count(*) as total')
                ->groupBy('source')
                ->pluck('total', 'source')
                ->all();

            $sourceText = $this->formatSourceSummary($sourceCounts);

            return $this->wrap(
                sprintf('<h1>Mascottes WoW %s — %d mascottes</h1>', e($category), count($items))
                .$this->breadcrumb($appUrl, [
                    ['Base de données', $dbUrl],
                    ['Mascottes', $dbUrl.'/mascottes'],
                    [$category, $dbUrl.'/mascottes/'.$categorySlug],
                ])
                .sprintf('<p>Découvrez les %d mascottes de combat %s de World of Warcraft en français.%s</p>', count($items), e($category), $sourceText)
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

    public function renderDecors(string $appUrl, ?string $categorySlug): ?string
    {
        $dbUrl = $appUrl.'/base-de-donnees';

        if ($categorySlug !== null) {
            $category = $this->findCategory($categorySlug, 'decors');

            if ($category === null) {
                return null;
            }

            /** @var list<array{name: string, url: string}> $items */
            $items = WowDecor::query()->where('is_active', true)
                ->where('category', $category)
                ->select(['name_fr', 'item_id'])
                ->get()
                ->map(fn (WowDecor $wowDecor): array => [
                    'name' => $wowDecor->name_fr,
                    'url' => $wowDecor->item_id
                        ? 'https://www.wowhead.com/fr/item='.$wowDecor->item_id
                        : 'https://www.wowhead.com/fr/search?q='.urlencode($wowDecor->name_fr),
                ])
                ->all();

            /** @var array<string, int> $sourceCounts */
            $sourceCounts = WowDecor::query()->where('is_active', true)
                ->where('category', $category)
                ->whereNotNull('source')
                ->selectRaw('source, count(*) as total')
                ->groupBy('source')
                ->pluck('total', 'source')
                ->all();

            $sourceText = $this->formatSourceSummary($sourceCounts);

            return $this->wrap(
                sprintf('<h1>Décorations WoW %s — %d décorations</h1>', e($category), count($items))
                .$this->breadcrumb($appUrl, [
                    ['Base de données', $dbUrl],
                    ['Décorations', $dbUrl.'/decorations'],
                    [$category, $dbUrl.'/decorations/'.$categorySlug],
                ])
                .sprintf('<p>Retrouvez les %d décorations %s de World of Warcraft en français.%s</p>', count($items), e($category), $sourceText)
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

    public function renderAppearances(string $appUrl, ?string $slotSlug): ?string
    {
        $dbUrl = $appUrl.'/base-de-donnees';

        if ($slotSlug !== null) {
            $slot = $this->findCategory($slotSlug, 'appearances');

            if ($slot === null) {
                return null;
            }

            /** @var list<array{name: string, url: string}> $items */
            $items = WowAppearance::query()->where('is_active', true)
                ->where('slot', $slot)
                ->select(['name_fr', 'item_id'])
                ->orderBy('name_fr')
                ->get()
                ->map(fn (WowAppearance $wowAppearance): array => [
                    'name' => $wowAppearance->name_fr,
                    'url' => $wowAppearance->item_id
                        ? 'https://www.wowhead.com/fr/item='.$wowAppearance->item_id
                        : 'https://www.wowhead.com/fr/search?q='.urlencode($wowAppearance->name_fr),
                ])
                ->all();

            return $this->wrap(
                sprintf('<h1>Transmogrification WoW %s — %d apparences</h1>', e($slot), count($items))
                .$this->breadcrumb($appUrl, [
                    ['Base de données', $dbUrl],
                    ['Garde-robe', $dbUrl.'/garde-robe'],
                    [$slot, $dbUrl.'/garde-robe/'.$slotSlug],
                ])
                .sprintf('<p>Retrouvez les %d apparences d\'équipement %s de World of Warcraft en français.</p>', count($items), e($slot))
                .$this->itemList($items),
            );
        }

        /** @var array<string, int> $slots */
        $slots = WowAppearance::query()->where('is_active', true)
            ->whereNotNull('slot')
            ->selectRaw('slot, count(*) as total')
            ->groupBy('slot')
            ->pluck('total', 'slot')
            ->all();

        $count = WowAppearance::query()->where('is_active', true)->count();

        return $this->wrap(
            sprintf('<h1>Transmogrification WoW — Liste complète des %s apparences en français</h1>', number_format($count, 0, ',', "\u{202f}"))
            .$this->breadcrumb($appUrl, [
                ['Base de données', $dbUrl],
                ['Garde-robe', $dbUrl.'/garde-robe'],
            ])
            .'<p>Toutes les apparences d\'équipement (transmog) de World of Warcraft en français, classées par emplacement.</p>'
            .$this->categoryLinks($dbUrl.'/garde-robe', $slots),
        );
    }

    public function renderProfessions(string $appUrl, ?string $professionSlug): ?string
    {
        $dbUrl = $appUrl.'/base-de-donnees';

        if ($professionSlug !== null) {
            $profession = WowProfession::query()->where('is_active', true)
                ->get()
                ->first(fn (WowProfession $wowProfession): bool => $this->slugify($wowProfession->name_fr) === $professionSlug);

            if (! $profession instanceof WowProfession) {
                return null;
            }

            $recipeCount = WowRecipe::query()->where('is_active', true)
                ->where('profession_id', $profession->id)
                ->count();

            /** @var list<array{name: string, url: string}> $items */
            $items = WowRecipe::query()->where('is_active', true)
                ->where('profession_id', $profession->id)
                ->select(['name_fr', 'wowhead_spell_id'])
                ->get()
                ->map(fn (WowRecipe $wowRecipe): array => [
                    'name' => $wowRecipe->name_fr,
                    'url' => $wowRecipe->wowhead_spell_id
                        ? 'https://www.wowhead.com/fr/spell='.$wowRecipe->wowhead_spell_id
                        : 'https://www.wowhead.com/fr/search?q='.urlencode($wowRecipe->name_fr),
                ])
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
     * @param  list<array{name: string, url: string}>|list<string>  $items
     */
    private function itemList(array $items, int $limit = 200): string
    {
        if ($items === []) {
            return '';
        }

        $displayed = array_slice($items, 0, $limit);
        $html = '<ul>';
        foreach ($displayed as $item) {
            if (is_array($item)) {
                $html .= sprintf('<li><a href="%s">%s</a></li>', $item['url'], e($item['name']));
            } else {
                $html .= sprintf('<li>%s</li>', e($item));
            }
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

    private function findCategory(string $slug, string $context): ?string
    {
        /** @var list<string> $candidates */
        $candidates = match ($context) {
            'mounts' => WowMount::query()->where('is_active', true)
                ->whereNotNull('category')->distinct()->pluck('category')->all(),
            'pets' => WowPet::query()->where('is_active', true)
                ->whereNotNull('category')->distinct()->pluck('category')->all(),
            'decors' => WowDecor::query()->where('is_active', true)
                ->whereNotNull('category')->distinct()->pluck('category')->all(),
            'appearances' => WowAppearance::query()->where('is_active', true)
                ->whereNotNull('slot')->distinct()->pluck('slot')->all(),
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

    /**
     * @param  array<string, int>  $sourceCounts
     */
    private function formatSourceSummary(array $sourceCounts): string
    {
        if ($sourceCounts === []) {
            return '';
        }

        $parts = [];
        foreach ($sourceCounts as $source => $count) {
            $parts[] = sprintf('%d %s', $count, e(mb_strtolower($source)));
        }

        return ' Sources : '.implode(', ', array_slice($parts, 0, 5)).'.';
    }
}
