<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowAchievement extends Model
{
    protected $table = 'wow_achievements';
    protected $primaryKey = 'id';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name_fr',
        'expansion_id',
        'category_name',
        'is_active',
    ];

    protected $casts = [
        'expansion_id' => 'integer',
        'is_active' => 'boolean',
    ];
}