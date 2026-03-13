<?php

declare(strict_types=1);

namespace App\Application\DTOs;

class AccountScoreProgress
{
    public int $processed = 0;

    /** @var list<string> */
    public array $errors = [];

    /** @var array<int, true> */
    public array $completedQuestIds = [];

    /** @var array<int, true> */
    public array $completedAchievementIds = [];

    /** @var array<int, array{completed: int, total: int}> */
    public array $bestReputations = [];

    /** @var array<int, array{quests: array{total: int, zones: list<array<string, mixed>>}, achievements: array{total: int, categories: list<array<string, mixed>>}, reputations: array{total: int}}>|null */
    public ?array $collectionsTotals = null;

    /** @var array<int, array<string, mixed>>|null */
    public ?array $accountMounts = null;

    /** @var array<int, array<string, mixed>>|null */
    public ?array $accountPets = null;

    /** @var array<int, array<string, mixed>>|null */
    public ?array $accountDecor = null;

    /** @var array<int, array<string, mixed>> */
    public array $professionMap = [];

    /** @var array<int, true> */
    public array $completedRecipeIds = [];

    /** @var array<int, array<int, array{skill_points: int, max_skill_points: int}>> */
    public array $bestSkillPoints = [];

    /** @var array{completed: int, total: int}|null */
    public ?array $bestProfessionStats = null;

    /**
     * @param  list<array{realmSlug: string, name: string}>  $characters
     */
    public function __construct(
        public readonly array $characters,
    ) {}

    public function mergeProfile(CharacterProfileDTO $characterProfileDTO): void
    {
        $this->processed++;

        $this->accountMounts ??= $characterProfileDTO->mounts;
        $this->accountPets ??= $characterProfileDTO->pets;
        $this->accountDecor ??= $characterProfileDTO->decor;

        $this->mergeCollections($characterProfileDTO);
        $this->mergeProfessions($characterProfileDTO);
        $this->trackBestProfessionStats($characterProfileDTO);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildResult(): array
    {
        $collections = $this->rebuildCollections();

        $mounts = $this->accountMounts ?? [];
        $pets = $this->accountPets ?? [];
        $decor = $this->accountDecor ?? [];

        return [
            'collections' => $collections,
            'mounts' => $mounts,
            'pets' => $pets,
            'decor' => $decor,
            'professions' => $this->rebuildProfessions(),
            'mountsCount' => count(array_filter($mounts, fn (array $m): bool => ! empty($m['is_completed']))),
            'petsCount' => count(array_filter($pets, fn (array $p): bool => ! empty($p['is_completed']))),
            'decorCount' => count(array_filter($decor, fn (array $d): bool => ! empty($d['is_completed']))),
            'bestProfessionStats' => $this->bestProfessionStats,
            'characterCount' => $this->processed,
            'errors' => $this->errors,
            'cachedAt' => now()->toISOString(),
        ];
    }

    private function mergeCollections(CharacterProfileDTO $characterProfileDTO): void
    {
        $this->collectionsTotals ??= [];

        foreach ($characterProfileDTO->collections as $expId => $exp) {
            /** @var array{quests?: array{total?: int, zones?: list<array<string, mixed>>}, achievements?: array{total?: int, categories?: list<array<string, mixed>>}, reputations?: array{total?: int, completed?: int}} $exp */
            $this->initExpansionTotals($expId, $exp);
            $this->collectCompletedIds($exp);
            $this->mergeBestReputations($expId, $exp);
        }
    }

    /**
     * @param  array{quests?: array{total?: int, zones?: list<array<string, mixed>>}, achievements?: array{total?: int, categories?: list<array<string, mixed>>}, reputations?: array{total?: int, completed?: int}}  $exp
     */
    private function initExpansionTotals(int $expId, array $exp): void
    {
        if (isset($this->collectionsTotals[$expId])) {
            return;
        }

        $this->collectionsTotals[$expId] = [
            'quests' => [
                'total' => (int) ($exp['quests']['total'] ?? 0),
                'zones' => $exp['quests']['zones'] ?? [],
            ],
            'achievements' => [
                'total' => (int) ($exp['achievements']['total'] ?? 0),
                'categories' => $exp['achievements']['categories'] ?? [],
            ],
            'reputations' => [
                'total' => (int) ($exp['reputations']['total'] ?? 0),
            ],
        ];
    }

    /**
     * @param  array{quests?: array{total?: int, zones?: list<array<string, mixed>>}, achievements?: array{total?: int, categories?: list<array<string, mixed>>}, reputations?: array{total?: int, completed?: int}}  $exp
     */
    private function collectCompletedIds(array $exp): void
    {
        $this->extractCompletedFromGroups($exp['quests']['zones'] ?? [], $this->completedQuestIds);
        $this->extractCompletedFromGroups($exp['achievements']['categories'] ?? [], $this->completedAchievementIds);
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @param  array<int, true>  $target
     */
    private function extractCompletedFromGroups(array $groups, array &$target): void
    {
        foreach ($groups as $group) {
            /** @var list<array<string, mixed>> $items */
            $items = $group['items'] ?? [];
            $this->extractCompletedFromItems($items, $target);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<int, true>  $target
     */
    private function extractCompletedFromItems(array $items, array &$target): void
    {
        foreach ($items as $item) {
            if (! empty($item['is_completed']) && is_int($item['id'] ?? null)) {
                $target[$item['id']] = true;
            }
        }
    }

    /**
     * @param  array{quests?: array{total?: int, zones?: list<array<string, mixed>>}, achievements?: array{total?: int, categories?: list<array<string, mixed>>}, reputations?: array{total?: int, completed?: int}}  $exp
     */
    private function mergeBestReputations(int $expId, array $exp): void
    {
        $repCompleted = (int) ($exp['reputations']['completed'] ?? 0);
        $repTotal = (int) ($exp['reputations']['total'] ?? 0);

        if (! isset($this->bestReputations[$expId]) || $repCompleted > $this->bestReputations[$expId]['completed']) {
            $this->bestReputations[$expId] = ['completed' => $repCompleted, 'total' => $repTotal];
        }
    }

    private function trackBestProfessionStats(CharacterProfileDTO $characterProfileDTO): void
    {
        $recipeCompleted = 0;
        $recipeTotal = 0;
        $skillPoints = 0;
        $skillMax = 0;

        foreach ($characterProfileDTO->professions as $prof) {
            /** @var array<int|string, array<string, mixed>> $expansions */
            $expansions = is_array($prof['expansions'] ?? null) ? $prof['expansions'] : [];
            foreach ($expansions as $expansion) {
                $recipeCompleted += is_int($expansion['completed'] ?? null) ? $expansion['completed'] : 0;
                $recipeTotal += is_int($expansion['total'] ?? null) ? $expansion['total'] : 0;
                $skillPoints += is_int($expansion['skill_points'] ?? null) ? $expansion['skill_points'] : 0;
                $skillMax += is_int($expansion['max_skill_points'] ?? null) ? $expansion['max_skill_points'] : 0;
            }
        }

        $completed = $recipeTotal > 0 ? $recipeCompleted : $skillPoints;
        $total = $recipeTotal > 0 ? $recipeTotal : $skillMax;

        if ($total > 0 && ($this->bestProfessionStats === null || ($completed / $total) > ($this->bestProfessionStats['completed'] / max(1, $this->bestProfessionStats['total'])))) {
            $this->bestProfessionStats = ['completed' => $completed, 'total' => $total];
        }
    }

    private function mergeProfessions(CharacterProfileDTO $characterProfileDTO): void
    {
        foreach ($characterProfileDTO->professions as $prof) {
            /** @var array<string, mixed> $prof */
            if (! is_int($prof['profession_id'] ?? null)) {
                continue;
            }

            $pid = $prof['profession_id'];
            $this->professionMap[$pid] ??= $prof;

            /** @var array<int|string, array<string, mixed>> $expansions */
            $expansions = is_array($prof['expansions'] ?? null) ? $prof['expansions'] : [];
            foreach ($expansions as $expId => $expData) {
                $this->mergeProfessionExpansion($pid, (int) $expId, $expData);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $expData
     */
    private function mergeProfessionExpansion(int $pid, int $expId, array $expData): void
    {
        /** @var list<array<string, mixed>> $categories */
        $categories = is_array($expData['categories'] ?? null) ? $expData['categories'] : [];
        $this->extractCompletedFromGroups($categories, $this->completedRecipeIds);

        $sp = is_int($expData['skill_points'] ?? null) ? $expData['skill_points'] : 0;
        $msp = is_int($expData['max_skill_points'] ?? null) ? $expData['max_skill_points'] : 0;

        if (! isset($this->bestSkillPoints[$pid][$expId])
            || $sp > $this->bestSkillPoints[$pid][$expId]['skill_points']) {
            $this->bestSkillPoints[$pid][$expId] = [
                'skill_points' => $sp,
                'max_skill_points' => max($msp, $this->bestSkillPoints[$pid][$expId]['max_skill_points'] ?? 0),
            ];
        } else {
            $this->bestSkillPoints[$pid][$expId]['max_skill_points'] = max(
                $msp,
                $this->bestSkillPoints[$pid][$expId]['max_skill_points'],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rebuildProfessions(): array
    {
        $result = [];

        foreach ($this->professionMap as $pid => $prof) {
            /** @var array<int|string, array<string, mixed>> $expansions */
            $expansions = is_array($prof['expansions'] ?? null) ? $prof['expansions'] : [];
            $rebuiltExpansions = [];

            foreach ($expansions as $expId => $expData) {
                $rebuiltExpansions[(int) $expId] = $this->rebuildProfessionExpansion($pid, (int) $expId, $expData);
            }

            $result[] = array_merge($prof, ['expansions' => $rebuiltExpansions]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $expData
     * @return array<string, mixed>
     */
    private function rebuildProfessionExpansion(int $pid, int $expId, array $expData): array
    {
        /** @var list<array<string, mixed>> $categories */
        $categories = is_array($expData['categories'] ?? null) ? $expData['categories'] : [];

        $totalRecipes = 0;
        $completedRecipes = 0;
        $rebuiltCategories = [];

        foreach ($categories as $category) {
            $rebuilt = $this->rebuildItemGroup($category, $this->completedRecipeIds);
            $completedRecipes += $rebuilt['completed'];
            $totalRecipes += $rebuilt['total'];
            $rebuiltCategories[] = $rebuilt;
        }

        $bestSp = $this->bestSkillPoints[$pid][$expId] ?? null;

        return array_merge($expData, [
            'total' => $totalRecipes,
            'completed' => $completedRecipes,
            'categories' => $rebuiltCategories,
            'skill_points' => $bestSp !== null ? $bestSp['skill_points'] : 0,
            'max_skill_points' => $bestSp !== null ? $bestSp['max_skill_points'] : 0,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rebuildCollections(): array
    {
        if ($this->collectionsTotals === null) {
            return [];
        }

        $collections = [];

        foreach ($this->collectionsTotals as $expId => $expTotals) {
            $collections[$expId] = $this->rebuildExpansionCollections($expId, $expTotals);
        }

        return $collections;
    }

    /**
     * @param  array{quests: array{total: int, zones: list<array<string, mixed>>}, achievements: array{total: int, categories: list<array<string, mixed>>}, reputations: array{total: int}}  $expTotals
     * @return array<string, mixed>
     */
    private function rebuildExpansionCollections(int $expId, array $expTotals): array
    {
        $questCompleted = 0;
        $zones = [];
        foreach ($expTotals['quests']['zones'] as $zone) {
            $rebuilt = $this->rebuildItemGroup($zone, $this->completedQuestIds);
            $questCompleted += $rebuilt['completed'];
            $zones[] = $rebuilt;
        }

        $achCompleted = 0;
        $categories = [];
        foreach ($expTotals['achievements']['categories'] as $cat) {
            $rebuilt = $this->rebuildItemGroup($cat, $this->completedAchievementIds);
            $achCompleted += $rebuilt['completed'];
            $categories[] = $rebuilt;
        }

        $rep = $this->bestReputations[$expId] ?? ['completed' => 0, 'total' => $expTotals['reputations']['total']];

        return [
            'quests' => ['total' => $expTotals['quests']['total'], 'completed' => $questCompleted, 'zones' => $zones],
            'achievements' => ['total' => $expTotals['achievements']['total'], 'completed' => $achCompleted, 'categories' => $categories],
            'reputations' => ['completed' => $rep['completed'], 'total' => $rep['total']],
        ];
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<int, true>  $completedIds
     * @return array<string, mixed>&array{completed: int, total: int}
     */
    private function rebuildItemGroup(array $group, array $completedIds): array
    {
        /** @var list<array<string, mixed>> $rawItems */
        $rawItems = $group['items'] ?? [];
        $items = [];
        $completed = 0;

        foreach ($rawItems as $rawItem) {
            $id = is_int($rawItem['id'] ?? null) ? $rawItem['id'] : 0;
            $isCompleted = isset($completedIds[$id]);
            if ($isCompleted) {
                $completed++;
            }

            $items[] = array_merge($rawItem, ['is_completed' => $isCompleted]);
        }

        return array_merge($group, ['items' => $items, 'completed' => $completed, 'total' => count($items)]);
    }
}
