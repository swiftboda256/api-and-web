<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends BaseModel
{
    protected $fillable = [
        'user_id',
        'label',
        'location',
        'formatted_address',
        'place_id',
        'is_default',
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
