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

Artisan::command('furniture:materialize', function () {
    $layout = app(FurnitureLayout::class);

    return DB::transaction(function () use ($layout) {
        $row = DB::table('furniture_layouts')->lockForUpdate()->find(1);
        if (! $row) {
            $this->error('Furniture layout row not found.');

            return 1;
        }
        if (in_array($row->status, ['queued', 'building'], true)) {
            $this->warn("Furniture revision {$row->revision} is {$row->status}; wait until the build is finished.");

            return 1;
        }

        $stored = json_decode($row->overrides, true, flags: JSON_THROW_ON_ERROR);
        $items = $layout->items($stored);
        $fields = ['x', 'y', 'rotation', 'width', 'depth', 'height', 'base_z'];

        foreach ($items as &$item) {
            if ($item['floor'] === 'upper' && $item['id'] === 'bijspringer-skadis-1') {
                $item['base_z'] = .45;
            }
            if ($item['floor'] === 'upper' && $item['id'] === 'bijspringer-skadis-2') {
                $item['base_z'] = 1.00;
            }

            $key = $item['floor'].':'.$item['id'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $item)) {
                    $stored[$key][$field] = round((float) $item[$field], 5);
                }
            }
        }
        unset($item);

        DB::table('furniture_layouts')->where('id', 1)->update([
            'revision' => $row->revision + 1,
            'overrides' => json_encode($stored, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        $this->info('Materialized '.count($items).' furniture items into the database.');
        $this->info('SKADIS base heights stored as 45 cm and 100 cm.');
        $this->info('New furniture revision: '.($row->revision + 1).'.');

        return 0;
    });
})->purpose('Store the complete effective furniture state in the database before removing legacy hard-coded placement rules');
