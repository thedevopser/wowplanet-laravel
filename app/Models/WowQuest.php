<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowQuest extends Model
{
    protected $table = 'wow_quests';
    protected $primaryKey = 'id';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name_fr',
        'expansion_id',
        'zone_name',
        'is_active',
    ];

    protected $casts = [
        'expansion_id' => 'integer',
        'is_active' => 'boolean',
    ];
}