<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'file_url' => Storage::disk('public')->url($this->file_path),
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'expires_at' => $this->expires_at,
        ];
    }
}
