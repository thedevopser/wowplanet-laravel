<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\ExpansionId;

interface CollectionItem
{
    public function getExpansionId(): ExpansionId;

    public function getName(): string;

    public function getId(): int;
}
