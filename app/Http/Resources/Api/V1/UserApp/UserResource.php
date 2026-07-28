<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'other_name' => $this->other_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'status' => $this->status,
            'profile_completed' => $this->profile_completed,
            'referral_code' => $this->referral_code,
            'rider_profile' => $this->whenLoaded('riderProfile', fn () => new RiderProfileResource($this->riderProfile)),
            'vehicle' => $this->whenLoaded('riderProfile', fn () => $this->riderProfile?->vehicle
                ? new VehicleResource($this->riderProfile->vehicle)
                : null),
            'documents' => $this->whenLoaded('riderProfile', fn () => $this->riderProfile?->relationLoaded('documents')
                ? DocumentResource::collection($this->riderProfile->documents)
                : null),
            'ratings' => [
                'average' => $this->rating_avg,
                'total' => $this->rating_count,
            ],

        ];
    }
}
