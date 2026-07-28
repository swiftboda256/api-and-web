<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\RiderProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RiderProfile
 */
class RiderProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rider_ref' => $this->rider_ref,
            'national_id_number' => $this->national_id_number,
            'license_number' => $this->license_number,
            'license_expiry_at' => $this->license_expiry_at,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'kyc_status' => $this->kyc_status,
            'kyc_rejection_reason' => $this->kyc_rejection_reason,
            'availability_status' => $this->availability_status,
            'total_trips' => $this->total_trips,
            'total_earnings' => $this->total_earnings,
        ];
    }
}
