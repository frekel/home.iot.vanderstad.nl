<?php

use App\Services\FurnitureLayout;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('floorplan:rebuild', function () {
    $layout = app(FurnitureLayout::class);
    $state = $layout->state();

    if (in_array($state['status'], ['queued', 'building'], true)) {
        $this->warn("Floorplan revision {$state['revision']} is already {$state['status']}.");

        return 1;
    }

    $layout->build((int) $state['revision']);
    $this->info("Floorplan rebuild queued for revision {$state['revision']}.");

    return 0;
})->purpose('Regenerate all floorplan 3D models for the current furniture revision');

Artisan::command('floorplan:status', function () {
    $state = app(FurnitureLayout::class)->state();
    $upToDate = (int) $state['model_revision'] === (int) $state['revision']
        && $state['status'] === 'ready';

    $this->table(
        ['Revision', 'Model revision', 'Status', 'Up to date'],
        [[
            $state['revision'],
            $state['model_revision'] ?? '-',
            $state['status'],
            $upToDate ? 'yes' : 'no',
        ]],
    );

    if ($upToDate) {
        $this->info('Floorplan is up to date.');
    } elseif ($state['status'] === 'failed') {
        $this->error('Floorplan build failed.');
    } else {
        $this->warn('Floorplan model is not up to date yet.');
    }

    return 0;
})->purpose('Show the current floorplan revision and build status');

Artisan::command('furniture:fix-256-radiator', function () {
    $layout = app(FurnitureLayout::class);

    $revision = DB::transaction(function () {
        $row = DB::table('furniture_layouts')->lockForUpdate()->find(1);
        if (! $row) {
            throw new RuntimeException('Furniture layout row not found.');
        }
        if (in_array($row->status, ['queued', 'building'], true)) {
            throw new RuntimeException("Furniture revision {$row->revision} is {$row->status}; wait until the current build is finished.");
        }

        $stored = json_decode($row->overrides, true, flags: JSON_THROW_ON_ERROR);
        $sinkKey = 'upper:256';
        $radiatorKey = 'upper:256-radiator';

        foreach ([$sinkKey, $radiatorKey] as $key) {
            if (! isset($stored[$key])) {
                throw new RuntimeException("Missing database furniture state for {$key}.");
            }
        }
        foreach (['x', 'y', 'width', 'rotation'] as $field) {
            if (! array_key_exists($field, $stored[$sinkKey])) {
                throw new RuntimeException("Missing {$field} for {$sinkKey} in the database.");
            }
        }
        if (! array_key_exists('width', $stored[$radiatorKey])) {
            throw new RuntimeException("Missing width for {$radiatorKey} in the database.");
        }

        // On the furniture map, map-left is positive world X on this floor.
        // Keep the radiator on exactly the same bedroom-facing wall as V1-256.
        $stored[$radiatorKey]['x'] = round(
            (float) $stored[$sinkKey]['x']
            + ((float) $stored[$sinkKey]['width'] / 2)
            + ((float) $stored[$radiatorKey]['width'] / 2),
            5,
        );
        $stored[$radiatorKey]['y'] = round((float) $stored[$sinkKey]['y'], 5);
        $stored[$radiatorKey]['rotation'] = round((float) $stored[$sinkKey]['rotation'], 5);

        $revision = (int) $row->revision + 1;
        DB::table('furniture_layouts')->where('id', 1)->update([
            'revision' => $revision,
            'overrides' => json_encode($stored, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        return $revision;
    });

    $layout->build($revision);
    $this->info("V1-256-radiator stored directly left of V1-256 on the same bedroom wall.");
    $this->info("Floorplan rebuild queued for revision {$revision}.");

    return 0;
})->purpose('One-time database correction: place V1-256-radiator directly left of V1-256 on the same wall');