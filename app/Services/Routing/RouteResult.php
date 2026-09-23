<?php

namespace App\Services\Routing;

/**
 * The result of routing through an ordered list of waypoints.
 */
final readonly class RouteResult
{
    /**
     * @param  list<array{distance_km: float, duration_minutes: int}>  $legs  Per-leg breakdown, aligned with each consecutive waypoint pair (leg 0 = waypoint 0 -> 1, etc.)
     */
    public function __construct(
        public float $distanceKm,
        public int $durationMinutes,
        public ?string $polyline,
        public array $legs,
    ) {}
}
