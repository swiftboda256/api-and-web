<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\VehicleImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin VehicleImage
 */
class VehicleImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_url' => Storage::disk('public')->url($this->file_path),
        ];
    }
}
