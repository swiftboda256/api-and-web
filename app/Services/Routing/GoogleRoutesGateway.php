<?php

namespace App\Services\Routing;

use App\Services\Routing\Contracts\RoutingGateway;
use App\Support\Geo;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Wraps Google's Routes API (computeRoutes) to turn an ordered list of waypoints into an
 * actual driving route: total distance/duration, an encoded polyline, and a per-leg
 * breakdown between each consecutive pair of waypoints.
 *
 * If no API key is configured, or the API call fails, falls back to a straight-line
 * (Haversine) estimate at a fixed average speed so ride-share matching degrades gracefully
 * instead of failing outright — at the cost of detour-matching quality, which is logged.
 */
class GoogleRoutesGateway implements RoutingGateway
{
    private const string ENDPOINT = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    private const float FALLBACK_AVERAGE_SPEED_KMH = 25.0;

    public function computeRoute(array $waypoints): RouteResult
    {
        if (count($waypoints) < 2) {
            throw new InvalidArgumentException('computeRoute requires at least an origin and a destination.');
        }

        $apiKey = config('services.google.routes_api_key');

        if (! $apiKey) {
            Log::warning('routing.google_routes.missing_api_key', ['waypoint_count' => count($waypoints)]);

            return $this->fallbackRoute($waypoints);
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $apiKey,
            'X-Goog-FieldMask' => 'routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline,routes.legs.duration,routes.legs.distanceMeters',
        ])->post(self::ENDPOINT, [
            'origin' => $this->waypointPayload($waypoints[0]),
            'destination' => $this->waypointPayload($waypoints[array_key_last($waypoints)]),
            'intermediates' => array_map(
                fn (Point $point) => $this->waypointPayload($point),
                array_slice($waypoints, 1, -1),
            ),
            'travelMode' => 'DRIVE',
            'routingPreference' => 'TRAFFIC_AWARE',
            'units' => 'METRIC',
        ]);

        if ($response->failed()) {
            Log::error('routing.google_routes.request_failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->fallbackRoute($waypoints);
        }

        $route = $response->json('routes.0');

        if (! is_array($route)) {
            Log::error('routing.google_routes.no_route_found', ['waypoint_count' => count($waypoints)]);

            return $this->fallbackRoute($waypoints);
        }

        $legs = array_values(array_map(fn (array $leg): array => [
            'distance_km' => ((float) ($leg['distanceMeters'] ?? 0)) / 1000,
            'duration_minutes' => (int) ceil($this->parseSeconds($leg['duration'] ?? '0s') / 60),
        ], $route['legs'] ?? []));

        return new RouteResult(
            distanceKm: ((float) ($route['distanceMeters'] ?? 0)) / 1000,
            durationMinutes: (int) ceil($this->parseSeconds($route['duration'] ?? '0s') / 60),
            polyline: $route['polyline']['encodedPolyline'] ?? null,
            legs: $legs,
        );
    }

    /**
     * @param  list<Point>  $waypoints
     */
    private function fallbackRoute(array $waypoints): RouteResult
    {
        $legs = [];
        $totalDistanceKm = 0.0;

        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            $distanceKm = Geo::haversineKm($waypoints[$i], $waypoints[$i + 1]);
            $durationMinutes = (int) ceil(($distanceKm / self::FALLBACK_AVERAGE_SPEED_KMH) * 60);

            $legs[] = ['distance_km' => round($distanceKm, 2), 'duration_minutes' => $durationMinutes];
            $totalDistanceKm += $distanceKm;
        }

        return new RouteResult(
            distanceKm: round($totalDistanceKm, 2),
            durationMinutes: (int) ceil(($totalDistanceKm / self::FALLBACK_AVERAGE_SPEED_KMH) * 60),
            polyline: null,
            legs: $legs,
        );
    }

    /**
     * @return array{location: array{latLng: array{latitude: float, longitude: float}}}
     */
    private function waypointPayload(Point $point): array
    {
        return [
            'location' => [
                'latLng' => [
                    'latitude' => $point->getLatitude(),
                    'longitude' => $point->getLongitude(),
                ],
            ],
        ];
    }

    /**
     * Google returns durations as e.g. "123s".
     */
    private function parseSeconds(string $duration): float
    {
        return (float) rtrim($duration, 's');
    }
}
