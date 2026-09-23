<?php

namespace App\Services;

use App\Jobs\BuildFurniture;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FurnitureLayout
{
    /**
     * Furniture identity/render metadata.
     *
     * Numeric layout values are NOT authoritative here. Production values for
     * x, y, rotation, width, depth, height and base_z must exist in the database.
     */
    public function baseline(): array
    {
        $items = json_decode(file_get_contents(base_path('assets/blender/furniture.json')), true, flags: JSON_THROW_ON_ERROR)['items'];

        // Extra furniture that does not exist in the traced source file.
        // Only identity/render metadata lives here; layout values live in DB.
        $items = array_merge($items, [
            ['id' => '257', 'floor' => 'upper', 'kind' => 'cabinet', 'source_shape' => 'dresser', 'measured' => true],
            ['id' => '258', 'floor' => 'upper', 'kind' => 'tv', 'source_shape' => 'flat_tv', 'standing' => true, 'measured' => true],
            ['id' => '256-mirror', 'floor' => 'upper', 'kind' => 'mirror', 'source_shape' => 'user_specified', 'measured' => true],
            ['id' => '256-radiator', 'floor' => 'upper', 'kind' => 'Wandradiator', 'model_kind' => 'wall_radiator', 'source_shape' => 'user_specified', 'measured' => true],

            ['id' => '259', 'floor' => 'upper', 'kind' => 'Hangend bureaublad', 'model_kind' => 'hanging_desk', 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '260', 'floor' => 'upper', 'kind' => 'Monitor', 'model_kind' => 'monitor', 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '261', 'floor' => 'upper', 'kind' => 'Monitor', 'model_kind' => 'monitor', 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '262', 'floor' => 'upper', 'kind' => 'Laptop', 'model_kind' => 'laptop', 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '263', 'floor' => 'upper', 'kind' => 'Kledingkast', 'model_kind' => 'wardrobe_drawers', 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '264', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk', 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '265', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk', 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '266', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk', 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '267', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk', 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '268', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4', 'source_shape' => 'Anycubic Kobra 4', 'measured' => true],
            ['id' => '269', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4', 'source_shape' => 'Anycubic Kobra 4', 'measured' => true],
            ['id' => '270', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4', 'source_shape' => 'Anycubic Kobra 4', 'measured' => true],
            ['id' => '271', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4', 'source_shape' => 'Anycubic Kobra 4', 'measured' => true],
            ['id' => 'bijspringer-skadis-1', 'floor' => 'upper', 'kind' => 'SKADIS met filament', 'model_kind' => 'skadis_filament', 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => 'bijspringer-skadis-2', 'floor' => 'upper', 'kind' => 'SKADIS met filament', 'model_kind' => 'skadis_filament', 'source_shape' => 'user_measured', 'measured' => true],

            ['id' => 'laundry-basket', 'floor' => 'attic', 'kind' => 'Wasmand', 'model_kind' => 'laundry_basket', 'source_shape' => 'user_specified', 'measured' => true],
            ['id' => 'washok-rack-right', 'floor' => 'attic', 'kind' => 'Stellingkast washok rechts', 'model_kind' => 'storage_rack', 'source_shape' => 'user_specified', 'measured' => true],
            ['id' => 'washok-rack-bottom', 'floor' => 'attic', 'kind' => 'Stellingkast washok onder', 'model_kind' => 'storage_rack', 'source_shape' => 'user_specified', 'measured' => true],
            ['id' => 'washok-rack-left', 'floor' => 'attic', 'kind' => 'Stellingkast washok links', 'model_kind' => 'storage_rack', 'source_shape' => 'user_specified', 'measured' => true],
        ]);

        // These three attic pieces must remain independent editable items.
        foreach ($items as &$item) {
            if ($item['floor'] === 'attic' && in_array((string) $item['id'], ['191', '261', '201'], true)) {
                unset($item['group'], $item['group_name']);
            }
        }
        unset($item);

        return $items;
    }

    public function items(array $stored, bool $render = false): array
    {
        $valueFields = ['x', 'y', 'rotation', 'width', 'depth', 'height', 'base_z'];

        return array_map(function (array $item) use ($stored, $valueFields): array {
            $key = $item['floor'].':'.$item['id'];
            $values = $stored[$key] ?? null;

            if (! is_array($values)) {
                throw new RuntimeException("Furniture values missing from database for {$key}.");
            }

            foreach ($valueFields as $field) {
                if (! array_key_exists($field, $values)) {
                    throw new RuntimeException("Furniture value {$field} missing from database for {$key}.");
                }
                $item[$field] = $values[$field];
            }

            return $item;
        }, $this->baseline());
    }

    public function state(): array
    {
        $row = DB::table('furniture_layouts')->find(1);
        $stored = json_decode($row->overrides, true, flags: JSON_THROW_ON_ERROR);
        $models = [];
        $versions = json_decode(file_get_contents(resource_path('js/model-versions.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach (['ground', 'upper', 'attic'] as $floor) {
            $models[$floor] = $row->model_revision === null
                ? '/models/'.$floor.'.glb?v='.$versions[$floor]
                : '/dashboard/furniture/models/'.$row->model_revision.'/'.$floor;
        }

        return [
            'revision' => $row->revision,
            'model_revision' => $row->model_revision,
            'status' => $row->status,
            'items' => $this->items($stored),
            'models' => $models,
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

            $stored = json_decode($row->overrides, true, flags: JSON_THROW_ON_ERROR);
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

                // Values edited in the furniture panel are supplied in cm.
                // x/y/rotation remain untouched in the same DB record.
                foreach (['width', 'depth', 'height', 'base_z'] as $field) {
                    $stored[$key][$field] = round($item[$field] / 100, 5);
                }
            }

            DB::table('furniture_layouts')->where('id', 1)->update([
                'revision' => $row->revision + 1,
                'overrides' => json_encode($stored, JSON_THROW_ON_ERROR),
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
                'status' => 'queued',
                'started_at' => null,
                'updated_at' => now(),
            ]);

            BuildFurniture::dispatch($revision)->onConnection('furniture')->onQueue('furniture');
        });

        return $this->state();
    }
}
