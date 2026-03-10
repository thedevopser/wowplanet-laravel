<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $bnet_user_id
 * @property array<string, mixed> $data
 * @property int $character_count
 * @property \Illuminate\Support\Carbon|null $fetched_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static static updateOrCreate(array<string, mixed> $attributes, array<string, mixed> $values = [])
 */
class CrossCharacterData extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'cross_character_data';

    protected $primaryKey = 'bnet_user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'bnet_user_id',
        'data',
        'character_count',
        'fetched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'character_count' => 'integer',
            'fetched_at' => 'datetime',
        ];
    }
}
