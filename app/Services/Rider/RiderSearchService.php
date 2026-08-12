<?php

namespace App\Services\Rider;

use App\Models\Configuration;
use App\Models\RiderProfile;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;
use Illuminate\Database\Eloquent\Collection;

readonly class RiderSearchService
{
    private const int MAX_RESULTS = 50;

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, RiderProfile>
     */
    public function nearby(array $filters): Collection
    {
        $point = Point::makeGeodetic((float) $filters['latitude'], (float) $filters['longitude']);
        $radiusMeters = (float) ($filters['radius_km'] ?? Configuration::get('search_radius_km', 5)) * 1000;
        $distance = ST::distanceSphere('current_location', $point);

        return RiderProfile::query()
            ->select('rider_profiles.*')
            ->addSelect($distance->as('distance_meters'))
            ->where('kyc_status', 'approved')
            ->where('availability_status', 'online')
            ->whereNotNull('current_location')
            ->whereHas('vehicle', fn ($query) => $query
                ->where('status', 'approved')
                ->when($filters['vehicle_type_id'] ?? null, fn ($query, $vehicleTypeId) => $query->where('vehicle_type_id', $vehicleTypeId)))
            ->where($distance, '<=', $radiusMeters)
            ->orderBy($distance)
            ->with(['user', 'vehicle.vehicleType'])
            ->limit(self::MAX_RESULTS)
            ->get();
    }
}
