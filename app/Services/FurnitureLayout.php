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

        // Small cabinet + TV in Levi's room. Their exact position is derived
        // from the final measured bed dimensions in items(), so they remain
        // against the foot of the bed when dimensions change.
        $items[] = [
            'id' => '257', 'floor' => 'upper', 'kind' => 'cabinet',
            'x' => 3.08, 'y' => -0.33,
            'width' => 0.71, 'depth' => 0.34, 'height' => 0.87,
            'rotation' => 0, 'source_shape' => 'dresser', 'measured' => true,
        ];
        $items[] = [
            'id' => '258', 'floor' => 'upper', 'kind' => 'tv',
            'x' => 3.08, 'y' => -0.33,
            'width' => 0.62, 'depth' => 0.10, 'height' => 0.40,
            'rotation' => 180, 'source_shape' => 'flat_tv',
            'base_z' => 0.87, 'standing' => true, 'measured' => true,
        ];

        // Bijspringer (upper-floor office). The desk wall is mirrored from the
        // first draft. The MICKE/printer stack is placed flush against the
        // outside/window wall, with two SKADIS filament boards beside it.
        $items = array_merge($items, [
            [
                'id' => '259', 'floor' => 'upper', 'kind' => 'Hangend bureaublad', 'model_kind' => 'hanging_desk',
                'x' => -2.00, 'y' => 2.35, 'width' => 1.20, 'depth' => 0.80, 'height' => 0.04,
                'base_z' => 0.96, 'rotation' => 180, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '260', 'floor' => 'upper', 'kind' => 'Monitor', 'model_kind' => 'monitor',
                'x' => -2.27, 'y' => 2.67, 'width' => 0.54, 'depth' => 0.15, 'height' => 0.25,
                'base_z' => 1.00, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '261', 'floor' => 'upper', 'kind' => 'Monitor', 'model_kind' => 'monitor',
                'x' => -1.73, 'y' => 2.67, 'width' => 0.54, 'depth' => 0.15, 'height' => 0.25,
                'base_z' => 1.00, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '262', 'floor' => 'upper', 'kind' => 'Laptop', 'model_kind' => 'laptop',
                'x' => -2.00, 'y' => 2.20, 'width' => 0.36, 'depth' => 0.29, 'height' => 0.25,
                'base_z' => 1.00, 'rotation' => 180, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '263', 'floor' => 'upper', 'kind' => 'Kledingkast', 'model_kind' => 'wardrobe_drawers',
                'x' => -3.30, 'y' => 2.435, 'width' => 1.40, 'depth' => 0.63, 'height' => 1.88,
                'rotation' => 180, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '264', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk',
                'x' => -3.715, 'y' => 1.25, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75,
                'rotation' => 270, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '265', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk',
                'x' => -3.715, 'y' => 1.98, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75,
                'rotation' => 270, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '266', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk',
                'x' => -3.715, 'y' => 1.25, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75,
                'base_z' => 0.75, 'rotation' => 270, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '267', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk',
                'x' => -3.715, 'y' => 1.98, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75,
                'base_z' => 0.75, 'rotation' => 270, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => '268', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4',
                'x' => -3.715, 'y' => 1.25, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50,
                'rotation' => 270, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true,
            ],
            [
                'id' => '269', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4',
                'x' => -3.715, 'y' => 1.98, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50,
                'rotation' => 270, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true,
            ],
            [
                'id' => '270', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4',
                'x' => -3.715, 'y' => 1.25, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50,
                'base_z' => 0.75, 'rotation' => 270, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true,
            ],
            [
                'id' => '271', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4',
                'x' => -3.715, 'y' => 1.98, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50,
                'base_z' => 0.75, 'rotation' => 270, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true,
            ],
            [
                'id' => 'bijspringer-skadis-1', 'floor' => 'upper', 'kind' => 'SKADIS met filament', 'model_kind' => 'skadis_filament',
                'x' => -3.985, 'y' => 0.57, 'width' => 0.55, 'depth' => 0.03, 'height' => 0.55,
                'base_z' => 0.95, 'rotation' => 270, 'source_shape' => 'user_measured', 'measured' => true,
            ],
            [
                'id' => 'bijspringer-skadis-2', 'floor' => 'upper', 'kind' => 'SKADIS met filament', 'model_kind' => 'skadis_filament',
                'x' => -3.985, 'y' => 0.57, 'width' => 0.55, 'depth' => 0.03, 'height' => 0.55,
                'base_z' => 1.50, 'rotation' => 270, 'source_shape' => 'user_measured', 'measured' => true,
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
        $items = array_map(function ($item) use ($overrides, $render) {
            $dimensions = array_intersect_key($item, array_flip(['width', 'depth', 'height']));
            $item = array_replace($item, $overrides[$item['floor'].':'.$item['id']] ?? []);
            if ($render) {
                $item['original_dimensions'] = $dimensions;
            }

            return $item;
        }, $this->baseline());

        // Levi's measured bed (V1-239) always sits in the outside bottom-right
        // corner of the bedroom, flush with both walls. The small cabinet and
        // TV are centred directly against the foot of the bed.
        $bedIndex = array_search('239', array_column($items, 'id'), true);
        if ($bedIndex !== false && $items[$bedIndex]['floor'] === 'upper') {
            $bed = &$items[$bedIndex];
            $bed['rotation'] = 0;
            $bed['x'] = 4.0 - ($bed['width'] / 2);
            $bed['y'] = -2.75 + ($bed['depth'] / 2);
            $foot = $bed['y'] + ($bed['depth'] / 2);

            foreach ($items as &$item) {
                if ($item['floor'] !== 'upper' || ! in_array($item['id'], ['257', '258'], true)) {
                    continue;
                }
                $item['x'] = $bed['x'];
                $item['y'] = $foot + ($item['depth'] / 2);
                if ($item['id'] === '258') {
                    $item['rotation'] = 180;
                    $item['base_z'] = $items[array_search('257', array_column($items, 'id'), true)]['height'] ?? .87;
                }
            }
            unset($item, $bed);
        }

        // Lily's bed (Z-191) is rotated 90 degrees and sits exactly in the
        // lower-right corner of her attic bedroom: headboard against the
        // laundry partition and one side against the outside wall. Cabinet
        // Z-261 sits directly against the foot of the bed. The projector
        // screen Z-201 follows the cabinet and faces back towards the bed.
        $lilyBedIndex = array_search('191', array_column($items, 'id'), true);
        if ($lilyBedIndex !== false && $items[$lilyBedIndex]['floor'] === 'attic') {
            $lilyBed = &$items[$lilyBedIndex];
            $lilyBed['rotation'] = 90;
            $lilyBed['x'] = 1.8 - ($lilyBed['depth'] / 2);
            $lilyBed['y'] = -2.75 + ($lilyBed['width'] / 2);
            $bedFootX = $lilyBed['x'] - ($lilyBed['depth'] / 2);

            $cabinetIndex = array_search('261', array_column($items, 'id'), true);
            if ($cabinetIndex !== false && $items[$cabinetIndex]['floor'] === 'attic') {
                $cabinet = &$items[$cabinetIndex];
                $cabinet['rotation'] = 90;
                $cabinet['x'] = $bedFootX - ($cabinet['depth'] / 2);
                $cabinet['y'] = $lilyBed['y'];

                $screenIndex = array_search('201', array_column($items, 'id'), true);
                if ($screenIndex !== false && $items[$screenIndex]['floor'] === 'attic') {
                    $screen = &$items[$screenIndex];
                    $screen['rotation'] = 270;
                    $screen['x'] = $bedFootX - ($screen['depth'] / 2) - .005;
                    $screen['y'] = $lilyBed['y'];
                    $screen['base_z'] = $cabinet['height'] + .05;
                    unset($screen);
                }
                unset($cabinet);
            }
            unset($lilyBed);
        }

        return $items;
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
