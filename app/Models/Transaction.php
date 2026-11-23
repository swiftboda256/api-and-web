<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
 * @property CarbonImmutable|null $next_status_check_at
 * @property int $status_check_attempts
 * @property string|null $last_gateway_status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Transaction extends BaseModel
{
    /** Hours after which Yo! asks to be contacted about an unresolved transaction. */
    private const int GATEWAY_OVERDUE_HOURS = 24;

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
        'next_status_check_at',
        'status_check_attempts',
        'last_gateway_status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'next_status_check_at' => 'immutable_datetime',
            'status_check_attempts' => 'integer',
        ];
    }

    /**
     * Sent to the gateway but still unresolved long enough to raise with it.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function gatewayOverdue(Builder $query): void
    {
        $query->where('status', 'pending')
            ->whereNotNull('gateway_reference')
            ->whereIn('transaction_type', ['withdrawal', 'reversal'])
            ->where('created_at', '<=', now()->subHours(self::GATEWAY_OVERDUE_HOURS));
    }

    public function isGatewayOverdue(): bool
    {
        return $this->status === 'pending'
            && $this->gateway_reference !== null
            && in_array($this->transaction_type, ['withdrawal', 'reversal'], true)
            && $this->created_at?->lte(now()->subHours(self::GATEWAY_OVERDUE_HOURS)) === true;
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
