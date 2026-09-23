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

        // User-measured first-floor furniture opposite the stairs, centred on
        // the 63 cm wall between the two bedroom doors.
        $items[] = [
            'id' => '257', 'floor' => 'upper', 'kind' => 'cabinet',
            'x' => -0.0349, 'y' => 0.4492,
            'width' => 0.71, 'depth' => 0.34, 'height' => 0.87,
            'rotation' => 0, 'source_shape' => 'dresser', 'measured' => true,
        ];
        $items[] = [
            'id' => '258', 'floor' => 'upper', 'kind' => 'tv',
            'x' => -0.0349, 'y' => 0.3292,
            'width' => 0.62, 'depth' => 0.10, 'height' => 0.40,
            'rotation' => 0, 'source_shape' => 'flat_tv',
            'base_z' => 0.87, 'standing' => true, 'measured' => true,
        ];

        // Bijspringer (upper-floor office). Entering from the landing, the
        // hanging desk and wardrobe are on the left/south wall. The stacked
        // MICKE units are centred against the opposite/window wall.
        $items = array_merge($items, [
            [
                'id' => '259', 'floor' => 'upper', 'kind' => 'Hangend bureaublad', 'model_kind' => 'hanging_desk',
                'x' => -1.95, 'y' => 0.6292, 'width' => 1.20, 'depth' => 0.80, 'height' => 0.04,
                'base_z' => 0.96, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '260', 'floor' => 'upper', 'kind' => 'Monitor', 'model_kind' => 'monitor',
                'x' => -2.22, 'y' => 0.37, 'width' => 0.54, 'depth' => 0.15, 'height' => 0.25,
                'base_z' => 1.00, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '261', 'floor' => 'upper', 'kind' => 'Monitor', 'model_kind' => 'monitor',
                'x' => -1.68, 'y' => 0.37, 'width' => 0.54, 'depth' => 0.15, 'height' => 0.25,
                'base_z' => 1.00, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '262', 'floor' => 'upper', 'kind' => 'Laptop', 'model_kind' => 'laptop',
                'x' => -1.95, 'y' => 0.77, 'width' => 0.36, 'depth' => 0.29, 'height' => 0.25,
                'base_z' => 1.00, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '263', 'floor' => 'upper', 'kind' => 'Kledingkast', 'model_kind' => 'wardrobe_drawers',
                'x' => -3.25, 'y' => 0.5442, 'width' => 1.40, 'depth' => 0.63, 'height' => 1.88,
                'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '264', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk',
                'x' => -3.0365, 'y' => 2.465, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75,
                'rotation' => 180, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '265', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk',
                'x' => -2.3065, 'y' => 2.465, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75,
                'rotation' => 180, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '266', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk',
                'x' => -3.0365, 'y' => 2.465, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75,
                'base_z' => 0.75, 'rotation' => 180, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '267', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk',
                'x' => -2.3065, 'y' => 2.465, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75,
                'base_z' => 0.75, 'rotation' => 180, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '268', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4',
                'x' => -3.0365, 'y' => 2.465, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50,
                'rotation' => 180, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true,
            ],
            [
                'id' => '269', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4',
                'x' => -2.3065, 'y' => 2.465, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50,
                'rotation' => 180, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true,
            ],
            [
                'id' => '270', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4',
                'x' => -3.0365, 'y' => 2.465, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50,
                'base_z' => 0.75, 'rotation' => 180, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true,
            ],
            [
                'id' => '271', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4',
                'x' => -2.3065, 'y' => 2.465, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50,
                'base_z' => 0.75, 'rotation' => 180, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true,
            ],
        ]);

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
            DB::table('furniture_layouts')->where('id', 1)->update([
                'revision' => $row->revision + 1, 'overrides' => json_encode($overrides, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });

        return $this->state();
    }

    public function build(int $revision): array
    {
        DB::transaction(function () use ($revision) {
            $row = DB::table('furniture_layouts')->lockForUpdate()->find(1);
            abort_if(in_array($row->status, ['queued', 'building']), 409, 'Er wordt al een plattegrond opgebouwd. Wacht tot deze klaar is.');
            abort_if((int) $row->revision !== $revision, 409, 'De meubels zijn elders gewijzigd. Herlaad de lijst voor je de plattegrond opbouwt.');
            DB::table('furniture_layouts')->where('id', 1)->update([
                'status' => 'queued', 'started_at' => null, 'updated_at' => now(),
            ]);
            BuildFurniture::dispatch($revision)->onConnection('furniture')->onQueue('furniture');
        });

        return $this->state();
    }
}
