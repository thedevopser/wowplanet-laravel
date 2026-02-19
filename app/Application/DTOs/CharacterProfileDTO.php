<?php

declare(strict_types=1);

namespace App\Application\DTOs;

readonly class CharacterProfileDTO
{
    /**
     * @param array<int, array<string, mixed>> $collections
     * @param array<int, array<string, mixed>> $mounts
     * @param array<int, array<string, mixed>> $pets
     * @param list<array<string, mixed>> $professions
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
    ) {
    }
}
