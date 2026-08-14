<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\TripCancellationReason;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TripCancellationReason
 */
class TripCancellationReasonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
        ];
    }
}
