<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowPet extends Model
{
    protected $table = 'wow_pets';
    protected $primaryKey = 'id';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name_fr',
        'source',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}