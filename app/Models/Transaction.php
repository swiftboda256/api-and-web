<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $wallet_id
 * @property string $method
 * @property string $direction
 * @property string $transaction_type
 * @property float $amount
 * @property float|null $balance_before
 * @property float|null $balance_after
 * @property string $currency_code
 * @property string|null $gateway
 * @property string|null $gateway_reference
 * @property string|null $external_reference
 * @property string|null $network_reference
 * @property string|null $phone
 * @property string|null $narration
 * @property string $status
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Transaction extends BaseModel
{
    protected $fillable = [
        'user_id',
        'wallet_id',
        'method',
        'direction',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'currency_code',
        'gateway',
        'gateway_reference',
        'external_reference',
        'network_reference',
        'phone',
        'narration',
        'reference_type',
        'reference_id',
        'status',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
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

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
