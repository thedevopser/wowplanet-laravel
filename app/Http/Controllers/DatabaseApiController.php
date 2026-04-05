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
use Illuminate\Database\Eloquent\Builder;
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

        return response()->json([
            'items' => $builder->get(),
            'categories' => $this->buildCategoryList(WowMount::class),
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
            if ($expansion instanceof ExpansionId) {
                $builder->where('expansion_id', $expansion->value);
            }
        }

        /** @var string|null $search */
        $search = $request->query('search');
        if ($search !== null && $search !== '') {
            $builder->where('name_fr', 'LIKE', '%'.$search.'%');
        }

        $perPage = min((int) ($request->query('per_page') ?: 50), 100);
        $lengthAwarePaginator = $builder->paginate($perPage);

        return response()->json([
            'items' => $lengthAwarePaginator->items(),
            'expansions' => $this->buildExpansionList(WowAchievement::class),
            'total' => $lengthAwarePaginator->total(),
            'current_page' => $lengthAwarePaginator->currentPage(),
            'last_page' => $lengthAwarePaginator->lastPage(),
            'per_page' => $lengthAwarePaginator->perPage(),
        ]);
    }

    public function quests(Request $request): JsonResponse
    {
        /** @var string|null $expansionSlug */
        $expansionSlug = $request->query('expansion');

        $builder = WowQuest::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'expansion_id', 'zone_name', 'faction'])
            ->orderBy('name_fr');

        if ($expansionSlug !== null && $expansionSlug !== '') {
            $expansion = ExpansionId::fromSlug($expansionSlug);
            if ($expansion instanceof ExpansionId) {
                $builder->where('expansion_id', $expansion->value);
            }
        }

        /** @var string|null $search */
        $search = $request->query('search');
        if ($search !== null && $search !== '') {
            $builder->where('name_fr', 'LIKE', '%'.$search.'%');
        }

        $perPage = min((int) ($request->query('per_page') ?: 50), 100);
        $lengthAwarePaginator = $builder->paginate($perPage);

        return response()->json([
            'items' => $lengthAwarePaginator->items(),
            'expansions' => $this->buildExpansionList(WowQuest::class),
            'total' => $lengthAwarePaginator->total(),
            'current_page' => $lengthAwarePaginator->currentPage(),
            'last_page' => $lengthAwarePaginator->lastPage(),
            'per_page' => $lengthAwarePaginator->perPage(),
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

        return response()->json([
            'items' => $builder->get(),
            'categories' => $this->buildCategoryList(WowPet::class),
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

        return response()->json([
            'items' => $builder->get(),
            'categories' => $this->buildCategoryList(WowDecor::class),
            'total' => WowDecor::query()->where('is_active', true)->count(),
        ]);
    }

    public function professions(): JsonResponse
    {
        $result = WowProfession::query()->where('is_active', true)
            ->select(['id', 'name_fr', 'type'])
            ->orderBy('name_fr')
            ->get()
            ->map(fn (WowProfession $wowProfession): array => [
                'id' => $wowProfession->id,
                'name_fr' => $wowProfession->name_fr,
                'type' => $wowProfession->type,
                'slug' => $this->slugify($wowProfession->name_fr),
                'recipe_count' => WowRecipe::query()->where('is_active', true)
                    ->where('profession_id', $wowProfession->id)
                    ->count(),
            ])
            ->all();

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
            if ($expansion instanceof ExpansionId) {
                $builder->where('expansion_id', $expansion->value);
            }
        }

        /** @var string|null $search */
        $search = $request->query('search');
        if ($search !== null && $search !== '') {
            $builder->where('name_fr', 'LIKE', '%'.$search.'%');
        }

        $perPage = min((int) ($request->query('per_page') ?: 50), 100);
        $lengthAwarePaginator = $builder->paginate($perPage);

        return response()->json([
            'items' => $lengthAwarePaginator->items(),
            'expansions' => $this->buildExpansionList(WowRecipe::class, $profession->id),
            'profession' => [
                'id' => $profession->id,
                'name_fr' => $profession->name_fr,
                'type' => $profession->type,
            ],
            'total' => $lengthAwarePaginator->total(),
            'current_page' => $lengthAwarePaginator->currentPage(),
            'last_page' => $lengthAwarePaginator->lastPage(),
            'per_page' => $lengthAwarePaginator->perPage(),
        ]);
    }

    public function subcategories(string $section): JsonResponse
    {
        $items = match ($section) {
            'mounts' => $this->buildCategoryList(WowMount::class),
            'pets' => $this->buildCategoryList(WowPet::class),
            'decors' => $this->buildCategoryList(WowDecor::class),
            'achievements' => array_map(fn (array $e): array => [
                'name' => $e['name'],
                'slug' => $e['slug'],
                'count' => $e['count'],
            ], $this->buildExpansionList(WowAchievement::class)),
            'quests' => array_map(fn (array $e): array => [
                'name' => $e['name'],
                'slug' => $e['slug'],
                'count' => $e['count'],
            ], $this->buildExpansionList(WowQuest::class)),
            'professions' => WowProfession::query()->where('is_active', true)
                ->select(['id', 'name_fr', 'type'])
                ->orderBy('name_fr')
                ->get()
                ->map(fn (WowProfession $wowProfession): array => [
                    'name' => $wowProfession->name_fr,
                    'slug' => $this->slugify($wowProfession->name_fr),
                    'count' => WowRecipe::query()->where('is_active', true)
                        ->where('profession_id', $wowProfession->id)->count(),
                    'type' => $wowProfession->type,
                ])
                ->all(),
            default => null,
        };

        abort_if($items === null, 404);

        return response()->json(['items' => $items]);
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

    /**
     * @param  class-string<WowAchievement|WowQuest|WowRecipe>  $modelClass
     * @return list<array{id: int, name: string, slug: string, count: int}>
     */
    private function buildExpansionList(string $modelClass, ?int $professionId = null): array
    {
        $expansions = [];

        foreach (ExpansionId::allSlugs() as $id => $slug) {
            /** @var Builder<WowAchievement|WowQuest|WowRecipe> $query */
            $query = $modelClass::query()->where('is_active', true)->where('expansion_id', $id);

            if ($professionId !== null) {
                $query->where('profession_id', $professionId);
            }

            $count = $query->count();
            if ($count > 0) {
                $expansions[] = [
                    'id' => $id,
                    'name' => (new ExpansionId($id))->toString(),
                    'slug' => $slug,
                    'count' => $count,
                ];
            }
        }

        return $expansions;
    }

    /**
     * @param  class-string<WowMount|WowPet|WowDecor>  $modelClass
     * @return list<array{name: string, slug: string, count: int}>
     */
    private function buildCategoryList(string $modelClass): array
    {
        /** @var array<int, array{category: string, items_count: int}> $rawCategories */
        $rawCategories = $modelClass::query()->where('is_active', true)
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as items_count')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->toArray();

        return array_values(array_map(fn (array $row): array => [
            'name' => $row['category'],
            'slug' => $this->slugify($row['category']),
            'count' => $row['items_count'],
        ], $rawCategories));
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

    private function deSlugify(string $slug, string $context): string
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
