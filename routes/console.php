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
