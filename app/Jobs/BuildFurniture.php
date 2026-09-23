<?php

namespace App\Jobs;

use App\Services\FurnitureLayout;
use App\Services\HouseLayout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class BuildFurniture implements ShouldQueue
{
    use Queueable;

    public int $timeout = 960;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $revision) {}

    public function handle(FurnitureLayout $layout, ?HouseLayout $house = null): void
    {
        $house ??= app(HouseLayout::class);
        $row = DB::table('furniture_layouts')->find(1);
        if ((int) $row->revision !== $this->revision || $row->status === 'ready') {
            return;
        }
        DB::table('furniture_layouts')->where('id', 1)->update(['status' => 'building', 'started_at' => now(), 'updated_at' => now()]);
        $directory = config('furniture.build_path').'/'.$this->revision;
        File::ensureDirectoryExists($directory);
        file_put_contents($directory.'/input.json', json_encode([
            'house' => $house->data(),
            'items' => $layout->items(render: true),
        ], JSON_THROW_ON_ERROR));
        $process = new Process([
            config('furniture.blender'), '--background', '--threads', '2', '--python-exit-code', '1', '--python', base_path('assets/blender/build_house.py'),
        ], base_path(), ['FURNITURE_INPUT' => $directory.'/input.json', 'FURNITURE_OUTPUT' => $directory], null, 900);
        $log = fopen($directory.'/build.log', 'w');
        try {
            $process->mustRun(function ($type, $output) use ($log) {
                fwrite($log, $output);
            });
            foreach (['ground', 'upper', 'attic'] as $floor) {
                $path = $directory.'/'.$floor.'.glb';
                if (! is_file($path) || filesize($path) < 20 || file_get_contents($path, false, null, 0, 4) !== 'glTF') {
                    throw new \RuntimeException('Blender produced an incomplete model.');
                }
            }
            // Publish all floors together only after every model is complete.
            DB::table('furniture_layouts')->where('id', 1)->where('revision', $this->revision)->update([
                'model_revision' => $this->revision, 'status' => 'ready', 'updated_at' => now(),
            ]);
        } finally {
            fclose($log);
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('furniture_layouts')->where('id', 1)->where('revision', $this->revision)->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
