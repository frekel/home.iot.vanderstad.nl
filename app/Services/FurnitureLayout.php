<?php

namespace App\Services;

use App\Jobs\BuildFurniture;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FurnitureLayout
{
    public function baseline(): array
    {
        $items = json_decode(file_get_contents(base_path('assets/blender/furniture.json')), true, flags: JSON_THROW_ON_ERROR)['items'];
        foreach ($items as &$item) {
            $item['base_z'] ??= match ($item['kind']) {
                'tv' => .9, 'computer' => .78, default => 0
            };
        }

        return $items;
    }

    public function items(array $overrides, bool $render = false): array
    {
        return array_map(function ($item) use ($overrides, $render) {
            $dimensions = array_intersect_key($item, array_flip(['width', 'depth', 'height']));
            $item = array_replace($item, $overrides[$item['floor'].':'.$item['id']] ?? []);
            if ($render) {
                $item['original_dimensions'] = $dimensions;
            }

            return $item;
        }, $this->baseline());
    }

    public function state(): array
    {
        $row = DB::table('furniture_layouts')->find(1);
        $models = [];
        $versions = json_decode(file_get_contents(resource_path('js/model-versions.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach (['ground', 'upper', 'attic'] as $floor) {
            $models[$floor] = $row->model_revision === null
                ? '/models/'.$floor.'.glb?v='.$versions[$floor]
                : '/dashboard/furniture/models/'.$row->model_revision.'/'.$floor;
        }

        return [
            'revision' => $row->revision, 'model_revision' => $row->model_revision, 'status' => $row->status,
            'items' => $this->items(json_decode($row->overrides, true, flags: JSON_THROW_ON_ERROR)), 'models' => $models,
        ];
    }

    public function save(array $data): array
    {
        DB::transaction(function () use ($data) {
            $row = DB::table('furniture_layouts')->lockForUpdate()->find(1);
            abort_if(in_array($row->status, ['queued', 'building']), 409, 'Er wordt al een plattegrond opgebouwd. Wacht tot deze klaar is.');
            abort_if((int) $row->revision !== $data['revision'], 409, 'De meubels zijn elders gewijzigd. Herlaad de lijst voor je opslaat.');
            $known = [];
            foreach ($this->baseline() as $item) {
                $known[$item['floor'].':'.$item['id']] = true;
            }
            $overrides = json_decode($row->overrides, true, flags: JSON_THROW_ON_ERROR);
            $seen = [];
            foreach ($data['items'] as $index => $item) {
                $key = $item['floor'].':'.$item['id'];
                if (! isset($known[$key]) || isset($seen[$key])) {
                    throw ValidationException::withMessages(["items.$index.id" => 'Onbekend of dubbel meubelnummer.']);
                }
                $seen[$key] = true;
                if ($item['height'] + $item['base_z'] > 500) {
                    throw ValidationException::withMessages(["items.$index.height" => 'Hoogte plus afstand vanaf de vloer mag maximaal 500 cm zijn.']);
                }
                foreach (['width', 'depth', 'height', 'base_z'] as $field) {
                    $overrides[$key][$field] = round($item[$field] / 100, 5);
                }
            }
            $revision = $row->revision + 1;
            DB::table('furniture_layouts')->where('id', 1)->update([
                'revision' => $revision, 'overrides' => json_encode($overrides, JSON_THROW_ON_ERROR),
                'status' => 'queued', 'started_at' => null, 'updated_at' => now(),
            ]);
            // Same database transaction as the saved dimensions: no lost queued build.
            BuildFurniture::dispatch($revision)->onConnection('furniture')->onQueue('furniture');
        });

        return $this->state();
    }
}
