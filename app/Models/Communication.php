<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One message an admin sent out (Admin → Communications) over one or more channels. Each
 * recipient × channel gets a CommunicationDelivery row recording whether it went out.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property list<string> $channels
 * @property list<string>|null $target_roles
 * @property list<int>|null $target_user_ids
 * @property string $status
 * @property int $recipients_count
 * @property int $sms_segments
 * @property int $sms_unit_cost
 * @property int $sms_estimated_cost
 * @property int|null $sms_cost_acknowledged_by
 * @property CarbonImmutable|null $sms_cost_acknowledged_at
 * @property int|null $sms_cost_acknowledged_amount
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Communication extends BaseModel
{
    public const string CHANNEL_PUSH = 'push';

    public const string CHANNEL_EMAIL = 'email';

    public const string CHANNEL_SMS = 'sms';

    public const array CHANNELS = [
        self::CHANNEL_PUSH => 'In-app push',
        self::CHANNEL_EMAIL => 'Email',
        self::CHANNEL_SMS => 'SMS',
    ];

    public const string STATUS_QUEUED = 'queued';

    public const string STATUS_SENDING = 'sending';

    public const string STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'title',
        'body',
        'channels',
        'target_roles',
        'target_user_ids',
        'status',
        'recipients_count',
        'sms_segments',
        'sms_unit_cost',
        'sms_estimated_cost',
        'sms_cost_acknowledged_by',
        'sms_cost_acknowledged_at',
        'sms_cost_acknowledged_amount',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'target_roles' => 'array',
            'target_user_ids' => 'array',
            'recipients_count' => 'integer',
            'sms_segments' => 'integer',
            'sms_unit_cost' => 'integer',
            'sms_estimated_cost' => 'integer',
            'sms_cost_acknowledged_at' => 'datetime',
            'sms_cost_acknowledged_amount' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The admin who ticked "I understand this SMS will cost ..." before sending.
     *
     * @return BelongsTo<User, $this>
     */
    public function smsCostAcknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sms_cost_acknowledged_by');
    }

    /**
     * @return HasMany<CommunicationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(CommunicationDelivery::class);
    }
}
