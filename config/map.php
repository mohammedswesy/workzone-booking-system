<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Map tiles (Leaflet)
    |--------------------------------------------------------------------------
    |
    | OSM's public tile servers are fine for light local/demo use only.
    | Configure a tile provider with an API key for production.
    |
    */

    'tile_url' => env('MAP_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'tile_attribution' => env(
        'MAP_TILE_ATTRIBUTION',
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    ),
    'tile_host' => env(
        'MAP_TILE_HOST',
        'https://tile.openstreetmap.org https://a.tile.openstreetmap.org https://b.tile.openstreetmap.org https://c.tile.openstreetmap.org'
    ),

    /** Max markers returned by the public map JSON endpoint. */
    'markers_limit' => (int) env('MAP_MARKERS_LIMIT', 100),

    'default_center' => [
        'lat' => (float) env('MAP_DEFAULT_LAT', 31.5017),
        'lng' => (float) env('MAP_DEFAULT_LNG', 34.4668),
        'zoom' => (int) env('MAP_DEFAULT_ZOOM', 11),
    ],
];
