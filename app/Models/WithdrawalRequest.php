<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $wallet_id
 * @property float $amount
 * @property float|null $balance_before
 * @property float|null $balance_after
 * @property string $channel
 * @property string $provider
 * @property string|null $account_identifier_masked
 * @property string|null $external_reference
 * @property string $status
 * @property CarbonImmutable|null $processed_at
 * @property string|null $rejection_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class WithdrawalRequest extends BaseModel
{
    protected $fillable = [
        'user_id',
        'wallet_id',
        'amount',
        'balance_before',
        'balance_after',
        'channel',
        'provider',
        'account_identifier_masked',
        'external_reference',
        'status',
        'processed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
