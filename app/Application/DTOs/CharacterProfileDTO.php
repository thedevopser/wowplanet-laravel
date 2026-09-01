<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use App\Domain\ValueObjects\CompletionScore;

readonly class CharacterProfileDTO
{
    /**
     * @param  array<int, array<string, mixed>>  $collections
     * @param  array<int, array<string, mixed>>  $mounts
     * @param  array<int, array<string, mixed>>  $pets
     * @param  list<array<string, mixed>>  $professions
     * @param  list<array<string, mixed>>  $decor
     * @param  array<string, mixed>|null  $mythicKeystone
     * @param  list<array<string, mixed>>|null  $raids
     * @param  list<int>  $completedQuestIds
     * @param  list<int>  $completedAchievementIds
     * @param  list<array{slot: string, slot_name: string, item_id: int, name: string, item_level: int, quality: string, icon_url: string|null}>  $equipment
     * @param  list<array{slot: string, category: string|null, total: int, completed: int}>  $appearances
     */
    public function __construct(
        public string $name,
        public string $realm,
        public string $race,
        public string $class,
        public int $classId,
        public int $level,
        public int $ilvl,
        public string $faction,
        public string $avatarUrl,
        public string $classIconUrl,
        public array $collections,
        public int $mountsCount,
        public int $petsCount,
        public int $achievementPoints = 0,
        public string $guild = '',
        public array $mounts = [],
        public array $pets = [],
        public array $professions = [],
        public int $decorCount = 0,
        public array $decor = [],
        public int $exaltedCount = 0,
        public ?array $mythicKeystone = null,
        public array $completedQuestIds = [],
        public array $completedAchievementIds = [],
        public array $equipment = [],
        public array $appearances = [],
        public int $appearancesCount = 0,
        public ?array $raids = null,
        public int $raidsCount = 0,
        public ?CompletionScore $score = null,
    ) {}
}
