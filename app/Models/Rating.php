<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends BaseModel
{
    protected $fillable = [
        'trip_id',
        'rater_id',
        'ratee_id',
        'rater_role',
        'score',
        'comment',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'tags' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_id');
    }
}
