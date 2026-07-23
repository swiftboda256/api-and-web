<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketMessage extends BaseModel
{
    protected $fillable = [
        'support_ticket_id',
        'sender_id',
        'message',
        'attachments',
        'is_internal_note',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_internal_note' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
