<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $label
 * @property Point $location
 * @property string|null $formatted_address
 * @property string|null $place_id
 * @property bool $is_default
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Address extends BaseModel
{
    protected $fillable = [
        'user_id',
        'label',
        'location',
        'formatted_address',
        'place_id',
        'is_default',
        'category',
    ];

    protected function casts(): array
    {
        return [
            'location' => Point::class,
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
