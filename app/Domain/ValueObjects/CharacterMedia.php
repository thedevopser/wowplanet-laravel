<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

readonly class CharacterMedia
{
    public function __construct(
        public string $avatarUrl,
        public string $insetUrl,
        public string $mainUrl,
    ) {
    }
}
