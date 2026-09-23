<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\DeliveryDetails;
use App\Models\TripPassenger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Items are a mix of TripPassenger (ride/ride_share) and DeliveryDetails (delivery/
 * delivery_share) -- each is wrapped with whichever resource matches its own type, since
 * $collects can't express a per-item resource choice.
 */
class TripCollection extends ResourceCollection
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator<int, TripPassenger|DeliveryDetails> $paginator */
        $paginator = $this->resource;

        return [
            'trips' => collect($paginator->items())
                ->map(fn (TripPassenger|DeliveryDetails $item) => $item instanceof DeliveryDetails
                    ? new DeliveryResource($item)
                    : new TripPassengerResource($item))
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
