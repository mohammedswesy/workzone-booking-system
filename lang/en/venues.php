<?php

return [
    'latitude' => 'Latitude',
    'longitude' => 'Longitude',
    'publish_requires_unit' => 'Publish requires at least one published room or desk.',
    'publish_requires_location' => 'Set a map pin (latitude and longitude) before publishing. Venues without coordinates do not appear on the map or in near-me results.',
    'validation' => [
        'lat_required_with' => 'Latitude is required when longitude is set (or clear both).',
        'lng_required_with' => 'Longitude is required when latitude is set (or clear both).',
        'lat_between' => 'Latitude must be between -90 and 90.',
        'lng_between' => 'Longitude must be between -180 and 180.',
        'lat_numeric' => 'Latitude must be a number.',
        'lng_numeric' => 'Longitude must be a number.',
        'lat_decimals' => 'Latitude may have at most 7 decimal places.',
        'lng_decimals' => 'Longitude may have at most 7 decimal places.',
        'coords_swapped' => 'These values look swapped. Latitude must be between -90 and 90 — paste "lat, lng" or check the order.',
    ],
    'nearMe' => [
        'lat_required_with' => 'Latitude is required when longitude is set (or clear both).',
        'lng_required_with' => 'Longitude is required when latitude is set (or clear both).',
        'lat_between' => 'Latitude must be between -90 and 90.',
        'lng_between' => 'Longitude must be between -180 and 180.',
        'lat_numeric' => 'Latitude must be a number.',
        'lng_numeric' => 'Longitude must be a number.',
        'radius_invalid' => 'Choose a radius of 5, 10, 25, 50, or 100 km (or Any).',
        'zero_zero' => '0,0 is not a valid location. Enter coordinates or use your location.',
    ],
];
