<?php

return [
    'url' => env('HOMEY_URL'),
    'token' => env('HOMEY_API_KEY'),
    'timeout' => 5,
    'access' => [
        ['id' => '423c3fe0-6416-42bc-bc90-6a75d803e520', 'name' => 'Achterdeur', 'zone' => 'Begane grond'],
        ['id' => '943287a7-9647-4b14-ae42-633264a49812', 'name' => 'Berging deur', 'zone' => 'Tuin'],
    ],
    'rooms' => [
        'living' => [
            'light' => env('HOMEY_LIVING_LIGHT_ID'),
            'climate' => env('HOMEY_LIVING_CLIMATE_ID'),
        ],
    ],
];
