<?php

return [
    'url' => env('HOMEY_URL'),
    'token' => env('HOMEY_API_KEY'),
    'timeout' => 5,
    'rooms' => [
        'living' => [
            'light' => env('HOMEY_LIVING_LIGHT_ID'),
            'climate' => env('HOMEY_LIVING_CLIMATE_ID'),
        ],
    ],
];
