<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\EmergencyContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmergencyContact
 */
class EmergencyContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'relationship' => $this->relationship,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at,
        ];
    }
}
