<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\CharacterMedia;

class Character
{
    public function __construct(
        public string $name,
        public string $realm,
        public string $race,
        public string $class,
        public int $level,
        public int $ilvl,
        public string $faction,
        public CharacterMedia $media,
    ) {}
}
