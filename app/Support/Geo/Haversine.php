<?php

namespace App\Support\Geo;

/**
 * Haversine distance in kilometers. Pure PHP — used for exact distance
 * after a bounding-box prefilter (MySQL + SQLite safe).
 */
final class Haversine
{
    public const EARTH_RADIUS_KM = 6371.0;

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $φ1 = deg2rad($lat1);
        $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);
        $Δλ = deg2rad($lng2 - $lng1);

        $a = sin($Δφ / 2) ** 2
            + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Approximate degree deltas for a radius (prefilter only).
     *
     * @return array{lat: float, lng: float}
     */
    public static function degreeDeltas(float $lat, float $radiusKm): array
    {
        $latDelta = $radiusKm / 111.0;
        $cos = cos(deg2rad($lat));
        $lngDelta = $cos == 0.0 ? 180.0 : $radiusKm / (111.0 * abs($cos));

        return [
            'lat' => min(90.0, $latDelta),
            'lng' => min(180.0, $lngDelta),
        ];
    }
}
