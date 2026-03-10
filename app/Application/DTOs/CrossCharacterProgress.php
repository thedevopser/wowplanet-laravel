<?php

declare(strict_types=1);

namespace App\Application\DTOs;

class CrossCharacterProgress
{
    /** @var array<int, string> quest_id => character_name */
    public array $completedQuestIds = [];

    /** @var array<int, string> achievement_id => character_name */
    public array $completedAchievementIds = [];

    /** @var array<int, true> */
    public array $completedRecipeIds = [];

    /** @var array<int, array{character_name: string, tier: int, raw: int, renown_level: int, standing_name: string, completed: bool}> */
    public array $bestFactionStandings = [];

    /** @var array<int, string> recipe_id => character_name */
    public array $recipeOwners = [];

    /** @var array<int, array<int, array{character_name: string, skill_points: int, max_skill_points: int}>> [profId][expId] */
    public array $skillPointOwners = [];

    /**
     * @param  array{questIds: list<int>, achievementIds: list<int>, reputations: array<string, mixed>, professions: array<string, mixed>}  $rawData
     */
    public function mergeCharacter(string $characterName, array $rawData): void
    {
        $this->mergeQuestIds($characterName, $rawData['questIds']);
        $this->mergeAchievementIds($characterName, $rawData['achievementIds']);
        $this->mergeReputations($characterName, $rawData['reputations']);
        $this->mergeProfessions($characterName, $rawData['professions']);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildResult(): array
    {
        return [
            'completedQuestIds' => array_keys($this->completedQuestIds),
            'completedAchievementIds' => array_keys($this->completedAchievementIds),
            'completedRecipeIds' => array_keys($this->completedRecipeIds),
            'questOwners' => $this->completedQuestIds,
            'achievementOwners' => $this->completedAchievementIds,
            'bestFactionStandings' => $this->bestFactionStandings,
            'recipeOwners' => $this->recipeOwners,
            'skillPointOwners' => $this->skillPointOwners,
        ];
    }

    /**
     * Merge from an existing CharacterProfileDTO (for piggyback).
     */
    public function mergeFromProfile(string $characterName, CharacterProfileDTO $characterProfileDTO): void
    {
        foreach ($characterProfileDTO->completedQuestIds as $id) {
            $this->completedQuestIds[$id] ??= $characterName;
        }

        foreach ($characterProfileDTO->completedAchievementIds as $id) {
            $this->completedAchievementIds[$id] ??= $characterName;
        }

        $this->mergeReputationsFromCollections($characterName, $characterProfileDTO->collections);
        $this->mergeProfessionsFromProfile($characterName, $characterProfileDTO->professions);
    }

    /**
     * @param  list<int>  $questIds
     */
    private function mergeQuestIds(string $characterName, array $questIds): void
    {
        foreach ($questIds as $questId) {
            $this->completedQuestIds[$questId] ??= $characterName;
        }
    }

    /**
     * @param  list<int>  $achievementIds
     */
    private function mergeAchievementIds(string $characterName, array $achievementIds): void
    {
        foreach ($achievementIds as $achievementId) {
            $this->completedAchievementIds[$achievementId] ??= $characterName;
        }
    }

    /**
     * @param  array{character_name: string, tier: int, raw: int, renown_level: int, standing_name: string, completed: bool}  $current
     */
    private function isBetterStanding(int $renownLevel, int $raw, array $current): bool
    {
        // If either has renown, compare by renown_level (renown is account-wide)
        if ($renownLevel > 0 || $current['renown_level'] > 0) {
            return $renownLevel > $current['renown_level'];
        }

        return $raw > $current['raw'];
    }

    /**
     * @param  array<string, mixed>  $reputationsResponse
     */
    private function mergeReputations(string $characterName, array $reputationsResponse): void
    {
        /** @var list<array<string, mixed>> $reputations */
        $reputations = $reputationsResponse['reputations'] ?? [];

        foreach ($reputations as $reputation) {
            /** @var array{id?: int} $faction */
            $faction = $reputation['faction'] ?? [];
            $factionId = (int) ($faction['id'] ?? 0);
            if ($factionId === 0) {
                continue;
            }

            /** @var array<string, mixed> $standing */
            $standing = $reputation['standing'] ?? [];
            $raw = is_int($standing['raw'] ?? null) ? $standing['raw'] : 0;
            $tier = is_int($standing['tier'] ?? null) ? $standing['tier'] : 0;
            $renownLevel = is_int($standing['renown_level'] ?? null) ? $standing['renown_level'] : 0;
            $maxStanding = is_int($standing['max'] ?? null) ? $standing['max'] : 1;

            if (! isset($this->bestFactionStandings[$factionId]) || $this->isBetterStanding($renownLevel, $raw, $this->bestFactionStandings[$factionId])) {
                $this->bestFactionStandings[$factionId] = [
                    'character_name' => $characterName,
                    'tier' => $tier,
                    'raw' => $raw,
                    'renown_level' => $renownLevel,
                    'standing_name' => is_string($standing['name'] ?? null) ? $standing['name'] : '',
                    'completed' => $tier >= 7 || ($renownLevel > 0 && $maxStanding === 0),
                ];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $professionsResponse
     */
    private function mergeProfessions(string $characterName, array $professionsResponse): void
    {
        /** @var list<array<string, mixed>> $primaries */
        $primaries = $professionsResponse['primaries'] ?? [];
        /** @var list<array<string, mixed>> $secondaries */
        $secondaries = $professionsResponse['secondaries'] ?? [];

        foreach (array_merge($primaries, $secondaries) as $charProfession) {
            /** @var array{id?: int} $profData */
            $profData = $charProfession['profession'] ?? [];
            $professionId = (int) ($profData['id'] ?? 0);
            if ($professionId === 0) {
                continue;
            }

            /** @var list<array<string, mixed>> $tiers */
            $tiers = $charProfession['tiers'] ?? [];

            foreach ($tiers as $tier) {
                $this->mergeProfessionTier($characterName, $professionId, $tier);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $tier
     */
    private function mergeProfessionTier(string $characterName, int $professionId, array $tier): void
    {
        /** @var array<string, mixed> $tierData */
        $tierData = is_array($tier['tier'] ?? null) ? $tier['tier'] : [];
        $tierId = is_int($tierData['id'] ?? null) ? $tierData['id'] : 0;

        $skillPoints = is_int($tier['skill_points'] ?? null) ? $tier['skill_points'] : 0;
        $maxSkillPoints = is_int($tier['max_skill_points'] ?? null) ? $tier['max_skill_points'] : 0;

        if ($skillPoints > 0 && (! isset($this->skillPointOwners[$professionId][$tierId]) || $skillPoints > $this->skillPointOwners[$professionId][$tierId]['skill_points'])) {
            $this->skillPointOwners[$professionId][$tierId] = [
                'character_name' => $characterName,
                'skill_points' => $skillPoints,
                'max_skill_points' => max($maxSkillPoints, $this->skillPointOwners[$professionId][$tierId]['max_skill_points'] ?? 0),
            ];
        }

        /** @var list<array<string, mixed>> $knownRecipes */
        $knownRecipes = $tier['known_recipes'] ?? [];

        foreach ($knownRecipes as $knownRecipe) {
            $recipeId = is_int($knownRecipe['id'] ?? null) ? $knownRecipe['id'] : 0;
            if ($recipeId === 0) {
                continue;
            }

            $this->completedRecipeIds[$recipeId] = true;
            $this->recipeOwners[$recipeId] ??= $characterName;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $collections
     */
    private function mergeReputationsFromCollections(string $characterName, array $collections): void
    {
        foreach ($collections as $collection) {
            /** @var array{factions?: list<array<string, mixed>>} $reputations */
            $reputations = $collection['reputations'] ?? [];
            /** @var list<array<string, mixed>> $factions */
            $factions = $reputations['factions'] ?? [];

            foreach ($factions as $faction) {
                $factionId = is_int($faction['id'] ?? null) ? $faction['id'] : 0;
                if ($factionId === 0) {
                    continue;
                }

                $raw = is_int($faction['raw'] ?? null) ? $faction['raw'] : 0;
                $tier = is_int($faction['tier'] ?? null) ? $faction['tier'] : 0;
                $renownLevel = is_int($faction['renown_level'] ?? null) ? $faction['renown_level'] : 0;

                if (! isset($this->bestFactionStandings[$factionId]) || $this->isBetterStanding($renownLevel, $raw, $this->bestFactionStandings[$factionId])) {
                    $this->bestFactionStandings[$factionId] = [
                        'character_name' => $characterName,
                        'tier' => $tier,
                        'raw' => $raw,
                        'renown_level' => $renownLevel,
                        'standing_name' => is_string($faction['standing_name'] ?? null) ? $faction['standing_name'] : '',
                        'completed' => ! empty($faction['completed']),
                    ];
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $professions
     */
    private function mergeProfessionsFromProfile(string $characterName, array $professions): void
    {
        foreach ($professions as $profession) {
            $professionId = is_int($profession['profession_id'] ?? null) ? $profession['profession_id'] : 0;
            if ($professionId === 0) {
                continue;
            }

            /** @var array<int|string, array<string, mixed>> $expansions */
            $expansions = is_array($profession['expansions'] ?? null) ? $profession['expansions'] : [];

            foreach ($expansions as $expId => $expData) {
                $skillPoints = is_int($expData['skill_points'] ?? null) ? $expData['skill_points'] : 0;
                $maxSkillPoints = is_int($expData['max_skill_points'] ?? null) ? $expData['max_skill_points'] : 0;

                if ($skillPoints > 0 && (! isset($this->skillPointOwners[$professionId][(int) $expId]) || $skillPoints > $this->skillPointOwners[$professionId][(int) $expId]['skill_points'])) {
                    $this->skillPointOwners[$professionId][(int) $expId] = [
                        'character_name' => $characterName,
                        'skill_points' => $skillPoints,
                        'max_skill_points' => max($maxSkillPoints, $this->skillPointOwners[$professionId][(int) $expId]['max_skill_points'] ?? 0),
                    ];
                }

                /** @var list<array<string, mixed>> $categories */
                $categories = is_array($expData['categories'] ?? null) ? $expData['categories'] : [];

                foreach ($categories as $category) {
                    /** @var list<array<string, mixed>> $items */
                    $items = $category['items'] ?? [];
                    foreach ($items as $item) {
                        if (! empty($item['is_completed']) && is_int($item['id'] ?? null)) {
                            $this->completedRecipeIds[$item['id']] = true;
                            $this->recipeOwners[$item['id']] ??= $characterName;
                        }
                    }
                }
            }
        }
    }
}
