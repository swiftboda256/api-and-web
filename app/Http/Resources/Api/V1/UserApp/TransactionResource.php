<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'direction' => $this->direction,
            'type' => $this->transaction_type,
            'amount' => $this->amount,
            'currency_code' => $this->currency_code,
            'gateway' => $this->gateway,
            'gateway_reference' => $this->gateway_reference,
            'narration' => $this->narration,
            'status' => $this->status,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at,
        ];
    }
}
