<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Rating
 */
class RatingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'rater_role' => $this->rater_role,
            'score' => $this->score,
            'comment' => $this->comment,
            'tags' => $this->tags,
            'created_at' => $this->created_at,
        ];
    }
}
