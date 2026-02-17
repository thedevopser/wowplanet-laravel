<?php

declare(strict_types=1);

namespace App\Application\DTOs;

readonly class CharacterProfileDTO
{
    /**
     * @param array<string, mixed> $collections
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
        public array $collections, // Grouped by expansion
        public int $mountsCount,
        public int $petsCount,
        public array $mounts = [],
        public array $pets = [],
    ) {}
}