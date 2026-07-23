<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
