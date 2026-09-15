<?php

use App\Services\HomeyClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'dashboard');
Route::get('/dashboard/status', function (HomeyClient $homey) {
    $empty = ['connected' => false, 'devices' => [], 'rooms' => $homey->rooms([]), 'layout' => config('homey_layout'), 'zones' => []];
    if (! $homey->configured()) {
        return response()->json($empty + ['message' => 'Add the Homey address and API key to the server environment.'])
            ->header('Cache-Control', 'no-store');
    }
    try {
        $devices = $homey->devices();
        // Zone permissions are optional: lack of them should not hide available device data.
        try {
            $zones = $homey->zones();
        } catch (Throwable) {
            $zones = [];
        }
        foreach ($devices as &$device) {
            $device['zone_name'] = $zones[$device['zone']]['name'] ?? '';
        }
        unset($device);

        return response()->json([
            'connected' => true, 'message' => 'Connected to Homey. Live data refreshes every 5 seconds.',
            'devices' => $devices, 'rooms' => $homey->rooms($devices), 'zones' => $zones, 'layout' => config('homey_layout'), 'fetched_at' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    } catch (Throwable) {
        return response()->json($empty + ['message' => 'Homey is unavailable. Live controls are disabled until it reconnects.'], 503)
            ->header('Cache-Control', 'no-store');
    }
})->middleware('throttle:120,1');

Route::put('/dashboard/rooms/{room}/light', function (Request $request, string $room, HomeyClient $homey) {
    abort_unless(array_key_exists($room, config('homey.rooms')) && filled(config("homey.rooms.$room.light")), 404);
    $request->validate(['value' => ['required', function ($attribute, $value, $fail) {
        if (! is_bool($value)) {
            $fail('The light state must be true or false.');
        }
    }]]);
    try {
        return response()->json($homey->setRoomLight($room, $request->input('value')))->header('Cache-Control', 'no-store');
    } catch (Throwable) {
        return response()->json(['message' => 'The light command could not be confirmed. Refresh before trying again.'], 503);
    }
})->middleware('throttle:20,1');
