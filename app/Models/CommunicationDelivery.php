<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The log line for one recipient on one channel of a Communication; "failed" carries the
 * reason. For SMS the status is final: EgoSMS answers Success/Failed in the send response
 * (no delivery callbacks). For push and email "sent" means FCM / the mail server accepted it.
 *
 * @property int $id
 * @property int $communication_id
 * @property int|null $user_id
 * @property string $channel
 * @property string|null $destination
 * @property string $status
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class CommunicationDelivery extends Model
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_SENT = 'sent';

    public const string STATUS_FAILED = 'failed';

    protected $fillable = [
        'communication_id',
        'user_id',
        'channel',
        'destination',
        'status',
        'failure_reason',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Communication, $this>
     */
    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
