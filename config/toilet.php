<?php

return [
    'device_id' => 'toilet-01',
    'write_token' => env('TOILET_WRITE_TOKEN'),
    'stale_after_seconds' => 90,
    'warn_low_seconds' => 120,
    'crit_low_seconds' => 600,
];
