<?php

namespace App\Support;

use Clickbar\Magellan\Data\Geometries\Point;

/**
 * Small pure-PHP geometry helpers for great-circle calculations that don't need a DB
 * round-trip (PostGIS/ST:: is preferred wherever the points are already in a query).
 */
final class Geo
{
    private const float EARTH_RADIUS_KM = 6371.0;

    public static function haversineKm(Point $from, Point $to): float
    {
        $latFrom = deg2rad($from->getLatitude());
        $latTo = deg2rad($to->getLatitude());
        $deltaLat = deg2rad($to->getLatitude() - $from->getLatitude());
        $deltaLng = deg2rad($to->getLongitude() - $from->getLongitude());

        $a = sin($deltaLat / 2) ** 2 + cos($latFrom) * cos($latTo) * sin($deltaLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Initial compass bearing in degrees [0, 360) travelling from $from towards $to.
     */
    public static function bearingDegrees(Point $from, Point $to): float
    {
        $latFrom = deg2rad($from->getLatitude());
        $latTo = deg2rad($to->getLatitude());
        $deltaLng = deg2rad($to->getLongitude() - $from->getLongitude());

        $y = sin($deltaLng) * cos($latTo);
        $x = cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($deltaLng);

        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }

    /**
     * Smallest angle in degrees [0, 180] between two compass bearings.
     */
    public static function bearingDifference(float $bearingA, float $bearingB): float
    {
        $diff = abs($bearingA - $bearingB);

        return min($diff, 360 - $diff);
    }
}
