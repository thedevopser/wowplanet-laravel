<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharacterVisit extends Model
{
    protected $fillable = [
        'realm_slug',
        'character_name',
        'display_name',
        'display_realm',
        'class_name',
        'level',
        'last_visited_at',
    ];

    protected function casts(): array
    {
        return [
            'last_visited_at' => 'datetime',
        ];
    }
}
