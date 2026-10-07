<?php

namespace App\Support;

/**
 * Allowed demo / factory city coordinates (no random world-wide points).
 * Cities: Gaza, Khan Younis, Dubai, Riyadh, Kuwait City.
 */
final class DemoCityCoordinates
{
    /**
     * @return list<array{city: string, name: string, address: string, lat: float, lng: float}>
     */
    public static function catalog(): array
    {
        return [
            [
                'city' => 'Gaza',
                'name' => 'Gaza Hub',
                'address' => 'Omar Al-Mukhtar St',
                'lat' => 31.5017,
                'lng' => 34.4668,
            ],
            [
                'city' => 'Khan Younis',
                'name' => 'Khan Younis Desk',
                'address' => 'Jamal Abdel Nasser St',
                'lat' => 31.3462,
                'lng' => 34.3063,
            ],
            [
                'city' => 'Dubai',
                'name' => 'Dubai Marina Desk',
                'address' => 'Marina Walk',
                'lat' => 25.0805,
                'lng' => 55.1403,
            ],
            [
                'city' => 'Riyadh',
                'name' => 'Riyadh Olaya Hub',
                'address' => 'Olaya St',
                'lat' => 24.7136,
                'lng' => 46.6753,
            ],
            [
                'city' => 'Kuwait City',
                'name' => 'Kuwait Sharq Loft',
                'address' => 'Gulf Road',
                'lat' => 29.3759,
                'lng' => 47.9774,
            ],
        ];
    }

    /**
     * @return array{city: string, name: string, address: string, lat: float, lng: float}
     */
    public static function random(): array
    {
        $catalog = self::catalog();

        return $catalog[array_rand($catalog)];
    }

    /**
     * Bounding boxes used to assert factory/demo points stay near the city centers.
     *
     * @return array<string, array{lat_min: float, lat_max: float, lng_min: float, lng_max: float}>
     */
    public static function bounds(): array
    {
        return [
            'Gaza' => ['lat_min' => 31.45, 'lat_max' => 31.55, 'lng_min' => 34.40, 'lng_max' => 34.55],
            'Khan Younis' => ['lat_min' => 31.30, 'lat_max' => 31.40, 'lng_min' => 34.25, 'lng_max' => 34.36],
            'Dubai' => ['lat_min' => 24.95, 'lat_max' => 25.30, 'lng_min' => 55.05, 'lng_max' => 55.40],
            'Riyadh' => ['lat_min' => 24.55, 'lat_max' => 24.90, 'lng_min' => 46.50, 'lng_max' => 46.85],
            'Kuwait City' => ['lat_min' => 29.25, 'lat_max' => 29.45, 'lng_min' => 47.85, 'lng_max' => 48.10],
        ];
    }

    public static function contains(string $city, float $lat, float $lng): bool
    {
        $box = self::bounds()[$city] ?? null;
        if ($box === null) {
            return false;
        }

        return $lat >= $box['lat_min'] && $lat <= $box['lat_max']
            && $lng >= $box['lng_min'] && $lng <= $box['lng_max'];
    }

    /**
     * @return list<string>
     */
    public static function cities(): array
    {
        return array_column(self::catalog(), 'city');
    }
}
