<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserDevice
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'device_type' => $this->device_type,
            'app_version' => $this->app_version,
            'fcm_token' => $this->fcm_token,
            'active' => $this->active,
            'last_seen_at' => $this->last_seen_at,
            'created_at' => $this->created_at,
        ];
    }
}
