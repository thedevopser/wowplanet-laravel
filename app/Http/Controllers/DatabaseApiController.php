<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\ValueObjects\ExpansionId;
use App\Models\WowAchievement;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatabaseApiController extends Controller
{
    public function mounts(Request $request): JsonResponse
    {
        $builder = WowMount::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'source', 'category', 'source_spell_id', 'icon_url'])
            ->orderBy('name_fr');

        /** @var string|null $category */
        $category = $request->query('category');
        if ($category !== null && $category !== '') {
            $builder->where('category', $this->deSlugify($category, 'mounts'));
        }

        $categories = [];

        /** @var array<int, array{category: string, items_count: int}> $rawCategories */
        $rawCategories = WowMount::query()->where('is_active', true)
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as items_count')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->toArray();

        foreach ($rawCategories as $rawCategory) {
            $categories[] = [
                'name' => $rawCategory['category'],
                'slug' => $this->slugify($rawCategory['category']),
                'count' => $rawCategory['items_count'],
            ];
        }

        return response()->json([
            'items' => $builder->get(),
            'categories' => $categories,
            'total' => WowMount::query()->where('is_active', true)->count(),
        ]);
    }

    public function achievements(Request $request): JsonResponse
    {
        /** @var string|null $expansionSlug */
        $expansionSlug = $request->query('expansion');

        $builder = WowAchievement::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'expansion_id', 'category_name', 'icon_url', 'points', 'faction'])
            ->orderBy('name_fr');

        if ($expansionSlug !== null && $expansionSlug !== '') {
            $expansion = ExpansionId::fromSlug($expansionSlug);
            if ($expansion instanceof \App\Domain\ValueObjects\ExpansionId) {
                $builder->where('expansion_id', $expansion->value);
            }
        }

        $expansions = [];
        foreach (ExpansionId::allSlugs() as $id => $slug) {
            $count = WowAchievement::query()->where('is_active', true)->where('expansion_id', $id)->count();
            if ($count > 0) {
                $expansions[] = [
                    'id' => $id,
                    'name' => (new ExpansionId($id))->toString(),
                    'slug' => $slug,
                    'count' => $count,
                ];
            }
        }

        return response()->json([
            'items' => $builder->get(),
            'expansions' => $expansions,
            'total' => WowAchievement::query()->where('is_active', true)->count(),
        ]);
    }

    public function quests(Request $request): JsonResponse
    {
        /** @var string|null $expansionSlug */
        $expansionSlug = $request->query('expansion');
        /** @var string|null $zoneSlug */
        $zoneSlug = $request->query('zone');

        $builder = WowQuest::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'expansion_id', 'zone_name', 'faction'])
            ->orderBy('name_fr');

        $expansion = null;
        if ($expansionSlug !== null && $expansionSlug !== '') {
            $expansion = ExpansionId::fromSlug($expansionSlug);
            if ($expansion instanceof \App\Domain\ValueObjects\ExpansionId) {
                $builder->where('expansion_id', $expansion->value);
            }
        }

        if ($zoneSlug !== null && $zoneSlug !== '' && $expansion instanceof \App\Domain\ValueObjects\ExpansionId) {
            $zoneName = $this->deSlugify($zoneSlug, 'quests', $expansion->value);
            $builder->where('zone_name', $zoneName);
        }

        $expansions = [];
        foreach (ExpansionId::allSlugs() as $id => $slug) {
            $count = WowQuest::query()->where('is_active', true)->where('expansion_id', $id)->count();
            if ($count > 0) {
                $expansions[] = [
                    'id' => $id,
                    'name' => (new ExpansionId($id))->toString(),
                    'slug' => $slug,
                    'count' => $count,
                ];
            }
        }

        $zones = [];
        if ($expansion instanceof \App\Domain\ValueObjects\ExpansionId) {
            /** @var array<int, array{zone_name: string, items_count: int}> $rawZones */
            $rawZones = WowQuest::query()->where('is_active', true)
                ->where('expansion_id', $expansion->value)
                ->whereNotNull('zone_name')
                ->selectRaw('zone_name, COUNT(*) as items_count')
                ->groupBy('zone_name')
                ->orderBy('zone_name')
                ->get()
                ->toArray();

            foreach ($rawZones as $rawZone) {
                $zones[] = [
                    'name' => $rawZone['zone_name'],
                    'slug' => $this->slugify($rawZone['zone_name']),
                    'count' => $rawZone['items_count'],
                ];
            }
        }

        return response()->json([
            'items' => $builder->get(),
            'expansions' => $expansions,
            'zones' => $zones,
            'total' => WowQuest::query()->where('is_active', true)->count(),
        ]);
    }

    public function pets(Request $request): JsonResponse
    {
        $builder = WowPet::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'source', 'category', 'creature_id', 'icon_url'])
            ->orderBy('name_fr');

        /** @var string|null $category */
        $category = $request->query('category');
        if ($category !== null && $category !== '') {
            $builder->where('category', $this->deSlugify($category, 'pets'));
        }

        $categories = [];

        /** @var array<int, array{category: string, items_count: int}> $rawCategories */
        $rawCategories = WowPet::query()->where('is_active', true)
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as items_count')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->toArray();

        foreach ($rawCategories as $rawCategory) {
            $categories[] = [
                'name' => $rawCategory['category'],
                'slug' => $this->slugify($rawCategory['category']),
                'count' => $rawCategory['items_count'],
            ];
        }

        return response()->json([
            'items' => $builder->get(),
            'categories' => $categories,
            'total' => WowPet::query()->where('is_active', true)->count(),
        ]);
    }

    public function decors(Request $request): JsonResponse
    {
        $builder = WowDecor::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'source', 'category', 'item_id', 'icon_url'])
            ->orderBy('name_fr');

        /** @var string|null $category */
        $category = $request->query('category');
        if ($category !== null && $category !== '') {
            $builder->where('category', $this->deSlugify($category, 'decors'));
        }

        $categories = [];

        /** @var array<int, array{category: string, items_count: int}> $rawCategories */
        $rawCategories = WowDecor::query()->where('is_active', true)
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as items_count')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->toArray();

        foreach ($rawCategories as $rawCategory) {
            $categories[] = [
                'name' => $rawCategory['category'],
                'slug' => $this->slugify($rawCategory['category']),
                'count' => $rawCategory['items_count'],
            ];
        }

        return response()->json([
            'items' => $builder->get(),
            'categories' => $categories,
            'total' => WowDecor::query()->where('is_active', true)->count(),
        ]);
    }

    public function professions(): JsonResponse
    {
        /** @var array<int, array{id: int, name_fr: string, type: string}> $professions */
        $professions = WowProfession::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'type'])
            ->orderBy('name_fr')
            ->get()
            ->toArray();

        $result = [];
        foreach ($professions as $profession) {
            $recipeCount = WowRecipe::query()->where('is_active', true)
                ->where('profession_id', $profession['id'])
                ->count();

            $result[] = [
                'id' => $profession['id'],
                'name_fr' => $profession['name_fr'],
                'type' => $profession['type'],
                'slug' => $this->slugify($profession['name_fr']),
                'recipe_count' => $recipeCount,
            ];
        }

        return response()->json([
            'professions' => $result,
            'total_professions' => WowProfession::query()->where('is_active', true)->count(),
            'total_recipes' => WowRecipe::query()->where('is_active', true)->count(),
        ]);
    }

    public function professionRecipes(Request $request): JsonResponse
    {
        /** @var string|null $professionSlug */
        $professionSlug = $request->query('profession');
        /** @var string|null $expansionSlug */
        $expansionSlug = $request->query('expansion');

        if ($professionSlug === null || $professionSlug === '') {
            return response()->json(['items' => [], 'expansions' => []]);
        }

        $professionName = $this->deSlugify($professionSlug, 'professions');
        $profession = WowProfession::query()->where('is_active', true)
            ->where('name_fr', $professionName)
            ->first();

        if (! $profession instanceof WowProfession) {
            return response()->json(['items' => [], 'expansions' => []]);
        }

        $builder = WowRecipe::query()->where('is_active', true)
            ->where('profession_id', $profession->id)
            ->select(['id', 'name_fr', 'expansion_id', 'category_name', 'faction', 'wowhead_spell_id'])
            ->orderBy('name_fr');

        if ($expansionSlug !== null && $expansionSlug !== '') {
            $expansion = ExpansionId::fromSlug($expansionSlug);
            if ($expansion instanceof \App\Domain\ValueObjects\ExpansionId) {
                $builder->where('expansion_id', $expansion->value);
            }
        }

        $expansions = [];
        foreach (ExpansionId::allSlugs() as $id => $slug) {
            $count = WowRecipe::query()->where('is_active', true)
                ->where('profession_id', $profession->id)
                ->where('expansion_id', $id)
                ->count();
            if ($count > 0) {
                $expansions[] = [
                    'id' => $id,
                    'name' => (new ExpansionId($id))->toString(),
                    'slug' => $slug,
                    'count' => $count,
                ];
            }
        }

        return response()->json([
            'items' => $builder->get(),
            'expansions' => $expansions,
            'profession' => [
                'id' => $profession->id,
                'name_fr' => $profession->name_fr,
                'type' => $profession->type,
            ],
        ]);
    }

    public function counts(): JsonResponse
    {
        return response()->json([
            'mounts' => WowMount::query()->where('is_active', true)->count(),
            'achievements' => WowAchievement::query()->where('is_active', true)->count(),
            'quests' => WowQuest::query()->where('is_active', true)->count(),
            'pets' => WowPet::query()->where('is_active', true)->count(),
            'decors' => WowDecor::query()->where('is_active', true)->count(),
            'professions' => WowProfession::query()->where('is_active', true)->count(),
            'recipes' => WowRecipe::query()->where('is_active', true)->count(),
        ]);
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

    private function deSlugify(string $slug, string $context, ?int $expansionId = null): string
    {
        /** @var list<string> $candidates */
        $candidates = match ($context) {
            'mounts' => WowMount::query()->where('is_active', true)
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->all(),
            'pets' => WowPet::query()->where('is_active', true)
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->all(),
            'decors' => WowDecor::query()->where('is_active', true)
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->all(),
            'professions' => WowProfession::query()->where('is_active', true)
                ->pluck('name_fr')
                ->all(),
            'quests' => WowQuest::query()->where('is_active', true)
                ->when($expansionId !== null, fn ($q) => $q->where('expansion_id', $expansionId))
                ->whereNotNull('zone_name')
                ->distinct()
                ->pluck('zone_name')
                ->all(),
            default => [],
        };

        foreach ($candidates as $candidate) {
            if ($this->slugify($candidate) === $slug) {
                return $candidate;
            }
        }

        $text = str_replace('-', ' ', $slug);

        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }
}
