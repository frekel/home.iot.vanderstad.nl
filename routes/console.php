<?php

use App\Services\FurnitureLayout;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
