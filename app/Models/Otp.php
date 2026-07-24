<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $channel
 * @property string|null $phone
 * @property string|null $email
 * @property string $code_hash
 * @property string $purpose
 * @property Carbon $expires_at
 * @property int $attempts
 */
class Otp extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'phone',
        'email',
        'code_hash',
        'purpose',
        'expires_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
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
