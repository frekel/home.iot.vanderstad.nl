<?php

use App\Services\FurnitureLayout;
use App\Services\HomeyClient;
use App\Services\HouseLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::view('/', 'dashboard');
Route::view('/measure', 'dashboard');
Route::get('/dashboard/status', function (HomeyClient $homey) {
    $empty = ['connected' => false, 'devices' => [], 'access' => $homey->access([]), 'rooms' => $homey->rooms([]), 'layout' => config('homey_layout'), 'zones' => []];
    if (! $homey->configured()) {
        return response()->json($empty + ['message' => 'Add the Homey address and API key to the server environment.'])
            ->header('Cache-Control', 'no-store');
    }
    try {
        $devices = $homey->devices();
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
            'devices' => $devices, 'access' => $homey->access($devices), 'rooms' => $homey->rooms($devices), 'zones' => $zones, 'layout' => config('homey_layout'), 'fetched_at' => now()->toIso8601String(),
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

Route::put('/dashboard/lights/{device}', function (Request $request, string $device, HomeyClient $homey) {
    $request->validate(['value' => ['required', function ($attribute, $value, $fail) {
        if (! is_bool($value)) {
            $fail('The light state must be true or false.');
        }
    }]]);
    try {
        return response()->json($homey->setLight($device, $request->input('value')))->header('Cache-Control', 'no-store');
    } catch (Throwable) {
        return response()->json(['message' => 'The light command could not be confirmed. Check the lamp before trying again.'], 503);
    }
})->middleware('throttle:60,1');

Route::get('/dashboard/energy', function (HomeyClient $homey) {
    $energy = $homey->energy();

    return response()->json($energy, $energy['available'] ? 200 : 503)->header('Cache-Control', 'no-store');
})->middleware('throttle:30,1');

Route::get('/dashboard/house', function (HouseLayout $layout) {
    return response()->json($layout->data())->header('Cache-Control', 'no-store');
})->middleware('throttle:120,1');

Route::get('/dashboard/furniture', function (FurnitureLayout $layout) {
    $state = $layout->state();

    // A manual floorplan:rebuild can replace a model without changing the
    // furniture revision. Add the generated file mtime so the browser cannot
    // keep serving the previous immutable GLB for that same revision.
    if ($state['model_revision'] !== null) {
        foreach (['ground', 'upper', 'attic'] as $floor) {
            $path = config('furniture.build_path').'/'.$state['model_revision'].'/'.$floor.'.glb';
            if (is_file($path)) {
                $state['models'][$floor] .= '?v='.filemtime($path);
            }
        }
    }

    return response()->json($state)->header('Cache-Control', 'no-store');
});
Route::put('/dashboard/furniture', function (Request $request, FurnitureLayout $layout) {
    $data = $request->validate([
        'revision' => ['required', 'integer', 'min:0'],
        'items' => ['present', 'array', 'max:200'],
        'items.*' => ['array:floor,id,width,depth,height,base_z,x,y,color'],
        'items.*.floor' => ['required', 'in:ground,upper,attic'],
        'items.*.id' => ['required', 'string', 'max:64'],
        'items.*.width' => ['required', 'numeric', 'min:5', 'max:1100'],
        'items.*.depth' => ['required', 'numeric', 'min:1', 'max:550'],
        'items.*.height' => ['required', 'numeric', 'min:1', 'max:400'],
        'items.*.base_z' => ['required', 'numeric', 'min:0', 'max:500'],
        'items.*.x' => ['sometimes', 'numeric', 'min:-2000', 'max:2000'],
        'items.*.y' => ['sometimes', 'numeric', 'min:-2000', 'max:2000'],
        'items.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
    ]);
    $data['revision'] = (int) $data['revision'];

    return response()->json($layout->save($data));
})->middleware('throttle:30,1');
Route::post('/dashboard/furniture/build', function (Request $request, FurnitureLayout $layout) {
    $data = $request->validate(['revision' => ['required', 'integer', 'min:0']]);

    return response()->json($layout->build((int) $data['revision']), 202);
})->middleware('throttle:10,1');
Route::get('/dashboard/furniture/models/{revision}/{floor}', function (int $revision, string $floor) {
    $published = DB::table('furniture_layouts')->value('model_revision');
    abort_if($published === null || $revision > $published, 404);
    $path = config('furniture.build_path').'/'.$revision.'/'.$floor.'.glb';
    abort_unless(is_file($path), 404);

    return response()->file($path, ['Content-Type' => 'model/gltf-binary', 'Cache-Control' => 'public, max-age=31536000, immutable']);
})->whereNumber('revision')->whereIn('floor', ['ground', 'upper', 'attic']);
