<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WithdrawalRequest
 */
class WithdrawalRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'channel' => $this->channel,
            'provider' => $this->provider,
            'account_identifier_masked' => $this->account_identifier_masked,
            'status' => $this->status,
            'processed_at' => $this->processed_at,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
        ];
    }
}
