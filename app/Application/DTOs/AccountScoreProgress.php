<?php

declare(strict_types=1);

namespace App\Application\DTOs;

class AccountScoreProgress
{
    public int $processed = 0;

    /** @var list<string> */
    public array $errors = [];

    /** @var list<int> */
    public array $completedQuestIds = [];

    /** @var list<int> */
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

    /**
     * @param  list<array{realmSlug: string, name: string}>  $characters
     */
    public function __construct(
        public readonly array $characters,
    ) {}

    public function mergeProfile(CharacterProfileDTO $characterProfileDTO): void
    {
        $this->processed++;

        if ($this->accountMounts === null) {
            $this->accountMounts = $characterProfileDTO->mounts;
        }

        if ($this->accountPets === null) {
            $this->accountPets = $characterProfileDTO->pets;
        }

        if ($this->accountDecor === null) {
            $this->accountDecor = $characterProfileDTO->decor;
        }

        $this->mergeCollections($characterProfileDTO);
        $this->mergeProfessions($characterProfileDTO);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildResult(): array
    {
        $questIdSet = array_flip(array_unique($this->completedQuestIds));
        $achIdSet = array_flip(array_unique($this->completedAchievementIds));

        $collections = $this->rebuildCollections($questIdSet, $achIdSet);

        $mounts = $this->accountMounts ?? [];
        $pets = $this->accountPets ?? [];
        $decor = $this->accountDecor ?? [];

        return [
            'collections' => $collections,
            'mounts' => $mounts,
            'pets' => $pets,
            'decor' => $decor,
            'professions' => array_values($this->professionMap),
            'mountsCount' => count(array_filter($mounts, fn (array $m): bool => ! empty($m['is_completed']))),
            'petsCount' => count(array_filter($pets, fn (array $p): bool => ! empty($p['is_completed']))),
            'decorCount' => count(array_filter($decor, fn (array $d): bool => ! empty($d['is_completed']))),
            'characterCount' => $this->processed,
            'errors' => $this->errors,
            'cachedAt' => now()->toISOString(),
        ];
    }

    private function mergeCollections(CharacterProfileDTO $characterProfileDTO): void
    {
        foreach ($characterProfileDTO->collections as $expId => $exp) {
            /** @var array{quests?: array{total?: int, zones?: list<array<string, mixed>>}, achievements?: array{total?: int, categories?: list<array<string, mixed>>}, reputations?: array{total?: int, completed?: int}} $exp */
            if ($this->collectionsTotals === null) {
                $this->collectionsTotals = [];
            }

            if (! isset($this->collectionsTotals[$expId])) {
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

            /** @var list<array<string, mixed>> $zones */
            $zones = $exp['quests']['zones'] ?? [];
            foreach ($zones as $zone) {
                /** @var list<array<string, mixed>> $items */
                $items = $zone['items'] ?? [];
                foreach ($items as $item) {
                    if (! empty($item['is_completed'])) {
                        $this->completedQuestIds[] = is_int($item['id'] ?? null) ? $item['id'] : 0;
                    }
                }
            }

            /** @var list<array<string, mixed>> $categories */
            $categories = $exp['achievements']['categories'] ?? [];
            foreach ($categories as $category) {
                /** @var list<array<string, mixed>> $items */
                $items = $category['items'] ?? [];
                foreach ($items as $item) {
                    if (! empty($item['is_completed'])) {
                        $this->completedAchievementIds[] = is_int($item['id'] ?? null) ? $item['id'] : 0;
                    }
                }
            }

            $repCompleted = is_int($exp['reputations']['completed'] ?? null) ? $exp['reputations']['completed'] : 0;
            $repTotal = is_int($exp['reputations']['total'] ?? null) ? $exp['reputations']['total'] : 0;
            if (! isset($this->bestReputations[$expId]) || $repCompleted > $this->bestReputations[$expId]['completed']) {
                $this->bestReputations[$expId] = ['completed' => $repCompleted, 'total' => $repTotal];
            }
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

            if (! isset($this->professionMap[$pid])) {
                $this->professionMap[$pid] = $prof;

                continue;
            }

            /** @var array<int|string, array<string, mixed>> $expansions */
            $expansions = is_array($prof['expansions'] ?? null) ? $prof['expansions'] : [];
            foreach ($expansions as $expId => $incoming) {
                /** @var array<string, mixed> $incoming */
                /** @var array<string, mixed> $profExpansions */
                $profExpansions = is_array($this->professionMap[$pid]['expansions'] ?? null) ? $this->professionMap[$pid]['expansions'] : [];
                if (! isset($profExpansions[$expId])) {
                    $profExpansions[$expId] = $incoming;
                } else {
                    /** @var array<string, mixed> $existing */
                    $existing = $profExpansions[$expId];
                    $profExpansions[$expId] = array_merge($existing, [
                        'completed' => max($this->safeInt($existing, 'completed'), $this->safeInt($incoming, 'completed')),
                        'skill_points' => max($this->safeInt($existing, 'skill_points'), $this->safeInt($incoming, 'skill_points')),
                        'total' => max($this->safeInt($existing, 'total'), $this->safeInt($incoming, 'total')),
                        'max_skill_points' => max($this->safeInt($existing, 'max_skill_points'), $this->safeInt($incoming, 'max_skill_points')),
                    ]);
                }

                $this->professionMap[$pid]['expansions'] = $profExpansions;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function safeInt(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;

        return is_int($value) ? $value : 0;
    }

    /**
     * @param  array<int, int>  $questIdSet
     * @param  array<int, int>  $achIdSet
     * @return array<int, array<string, mixed>>
     */
    private function rebuildCollections(array $questIdSet, array $achIdSet): array
    {
        $collections = [];

        if ($this->collectionsTotals === null) {
            return $collections;
        }

        foreach ($this->collectionsTotals as $expId => $expTotals) {
            $questCompleted = 0;
            $zones = [];

            foreach ($expTotals['quests']['zones'] as $zone) {
                /** @var list<array<string, mixed>> $rawItems */
                $rawItems = $zone['items'] ?? [];
                $items = [];
                $zoneCompleted = 0;

                foreach ($rawItems as $rawItem) {
                    $id = is_int($rawItem['id'] ?? null) ? $rawItem['id'] : 0;
                    $completed = isset($questIdSet[$id]);
                    if ($completed) {
                        $questCompleted++;
                        $zoneCompleted++;
                    }

                    $items[] = array_merge($rawItem, ['is_completed' => $completed]);
                }

                $zones[] = array_merge($zone, ['items' => $items, 'completed' => $zoneCompleted]);
            }

            $achCompleted = 0;
            $categories = [];

            foreach ($expTotals['achievements']['categories'] as $cat) {
                /** @var list<array<string, mixed>> $rawItems */
                $rawItems = $cat['items'] ?? [];
                $items = [];
                $catCompleted = 0;

                foreach ($rawItems as $rawItem) {
                    $id = is_int($rawItem['id'] ?? null) ? $rawItem['id'] : 0;
                    $completed = isset($achIdSet[$id]);
                    if ($completed) {
                        $achCompleted++;
                        $catCompleted++;
                    }

                    $items[] = array_merge($rawItem, ['is_completed' => $completed]);
                }

                $categories[] = array_merge($cat, ['items' => $items, 'completed' => $catCompleted]);
            }

            $rep = $this->bestReputations[$expId] ?? ['completed' => 0, 'total' => $expTotals['reputations']['total']];

            $collections[$expId] = [
                'quests' => ['total' => $expTotals['quests']['total'], 'completed' => $questCompleted, 'zones' => $zones],
                'achievements' => ['total' => $expTotals['achievements']['total'], 'completed' => $achCompleted, 'categories' => $categories],
                'reputations' => ['completed' => $rep['completed'], 'total' => $rep['total']],
            ];
        }

        return $collections;
    }
}
