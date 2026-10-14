<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\Configuration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Configuration
 */
class ConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'value_type' => $this->value_type,
            'description' => $this->description,
        ];
    }
}
