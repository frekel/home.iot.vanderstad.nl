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
            ['id' => '259', 'floor' => 'upper', 'kind' => 'Hangend bureaublad', 'model_kind' => 'hanging_desk', 'x' => -2.00, 'y' => 2.35, 'width' => 1.20, 'depth' => 0.80, 'height' => 0.04, 'base_z' => 0.96, 'rotation' => 180, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '260', 'floor' => 'upper', 'kind' => 'Monitor', 'model_kind' => 'monitor', 'x' => -2.27, 'y' => 2.67, 'width' => 0.54, 'depth' => 0.15, 'height' => 0.25, 'base_z' => 1.00, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '261', 'floor' => 'upper', 'kind' => 'Monitor', 'model_kind' => 'monitor', 'x' => -1.73, 'y' => 2.67, 'width' => 0.54, 'depth' => 0.15, 'height' => 0.25, 'base_z' => 1.00, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '262', 'floor' => 'upper', 'kind' => 'Laptop', 'model_kind' => 'laptop', 'x' => -2.00, 'y' => 2.20, 'width' => 0.36, 'depth' => 0.29, 'height' => 0.25, 'base_z' => 1.00, 'rotation' => 180, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '263', 'floor' => 'upper', 'kind' => 'Kledingkast', 'model_kind' => 'wardrobe_drawers', 'x' => -3.30, 'y' => 2.435, 'width' => 1.40, 'depth' => 0.63, 'height' => 1.88, 'rotation' => 180, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '264', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk', 'x' => -3.715, 'y' => 1.25, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75, 'rotation' => 270, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '265', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk', 'x' => -3.715, 'y' => 1.98, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75, 'rotation' => 270, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '266', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk', 'x' => -3.715, 'y' => 1.25, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75, 'base_z' => 0.75, 'rotation' => 270, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '267', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk', 'x' => -3.715, 'y' => 1.98, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75, 'base_z' => 0.75, 'rotation' => 270, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '268', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4', 'x' => -3.715, 'y' => 1.25, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50, 'rotation' => 270, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true],
            ['id' => '269', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4', 'x' => -3.715, 'y' => 1.98, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50, 'rotation' => 270, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true],
            ['id' => '270', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4', 'x' => -3.715, 'y' => 1.25, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50, 'base_z' => 0.75, 'rotation' => 270, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true],
            ['id' => '271', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4', 'x' => -3.715, 'y' => 1.98, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50, 'base_z' => 0.75, 'rotation' => 270, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true],
            ['id' => 'bijspringer-skadis-1', 'floor' => 'upper', 'kind' => 'SKADIS met filament', 'model_kind' => 'skadis_filament', 'x' => -3.985, 'y' => 0.57, 'width' => 0.55, 'depth' => 0.03, 'height' => 0.55, 'base_z' => 0.95, 'rotation' => 270, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => 'bijspringer-skadis-2', 'floor' => 'upper', 'kind' => 'SKADIS met filament', 'model_kind' => 'skadis_filament', 'x' => -3.985, 'y' => 0.57, 'width' => 0.55, 'depth' => 0.03, 'height' => 0.55, 'base_z' => 1.50, 'rotation' => 270, 'source_shape' => 'user_measured', 'measured' => true],
        ]);

        foreach ($items as &$item) {
            if ($item['floor'] === 'attic' && in_array((string) $item['id'], ['191', '261', '201'], true)) {
                unset($item['group'], $item['group_name']);
            }
            $item['base_z'] ??= match ($item['kind']) {
                'tv' => .9, 'computer' => .78, default => 0
            };
        }
        unset($item);

        return $items;
    }

    public function items(array $overrides, bool $render = false): array
    {
        // Saved measurements from the site are applied first. Everything below
        // only changes placement/rotation, so a freshly measured cabinet keeps
        // its exact dimensions when the floorplan is rebuilt.
        $items = array_map(function ($item) use ($overrides, $render) {
            $dimensions = array_intersect_key($item, array_flip(['width', 'depth', 'height']));
            $item = array_replace($item, $overrides[$item['floor'].':'.$item['id']] ?? []);
            if ($render) {
                $item['original_dimensions'] = $dimensions;
            }

            return $item;
        }, $this->baseline());

        $find = static function (string $id, string $floor) use (&$items): int|false {
            foreach ($items as $index => $item) {
                if ((string) $item['id'] === $id && $item['floor'] === $floor) {
                    return $index;
                }
            }

            return false;
        };

        // Levi's measured bed always sits in the outside bottom-right corner.
        $bedIndex = $find('239', 'upper');
        if ($bedIndex !== false) {
            $items[$bedIndex]['rotation'] = 0;
            $items[$bedIndex]['x'] = 4.0 - ($items[$bedIndex]['width'] / 2);
            $items[$bedIndex]['y'] = -2.75 + ($items[$bedIndex]['depth'] / 2);
            $foot = $items[$bedIndex]['y'] + ($items[$bedIndex]['depth'] / 2);

            foreach (['257', '258'] as $id) {
                $index = $find($id, 'upper');
                if ($index === false) {
                    continue;
                }
                $items[$index]['x'] = $items[$bedIndex]['x'];
                $items[$index]['y'] = $foot + ($items[$index]['depth'] / 2);
            }
            $tvIndex = $find('258', 'upper');
            $cabinetIndex = $find('257', 'upper');
            if ($tvIndex !== false) {
                $items[$tvIndex]['rotation'] = 180;
                $items[$tvIndex]['base_z'] = $cabinetIndex !== false ? $items[$cabinetIndex]['height'] : .87;
            }
        }

        // Final attic clear-wall coordinates:
        // x -3.40 = left knee wall, x 1.00 = Lily/Zolder side of Washok wall,
        // x 1.20..3.40 = Washok, y .55/.75 = the 20 cm Lily partition,
        // x -1.50/-1.30 = the 20 cm closet/Zolder partition.
        $leftKnee = -3.40;
        $roomRight = 1.00;
        $washokLeft = 1.20;
        $washokRight = 3.40;
        $lilyBottom = -2.75;
        $lilyTop = .55;
        $upperBottom = .75;
        $top = 2.75;
        $closetRight = -1.50;

        // Lily: bed in the lower-right corner, with Z-261 directly against the
        // foot. Z-201 stays aligned with the cabinet but all three remain
        // independent items (their old group is removed in baseline()).
        $lilyBedIndex = $find('191', 'attic');
        if ($lilyBedIndex !== false) {
            $items[$lilyBedIndex]['rotation'] = 90;
            $items[$lilyBedIndex]['x'] = $roomRight - ($items[$lilyBedIndex]['depth'] / 2);
            $items[$lilyBedIndex]['y'] = $lilyBottom + ($items[$lilyBedIndex]['width'] / 2);
            $bedFootX = $items[$lilyBedIndex]['x'] - ($items[$lilyBedIndex]['depth'] / 2);

            $cabinetIndex = $find('261', 'attic');
            if ($cabinetIndex !== false) {
                $items[$cabinetIndex]['rotation'] = 90;
                $items[$cabinetIndex]['x'] = $bedFootX - ($items[$cabinetIndex]['depth'] / 2);
                $items[$cabinetIndex]['y'] = $items[$lilyBedIndex]['y'];
            }

            $screenIndex = $find('201', 'attic');
            if ($screenIndex !== false) {
                $items[$screenIndex]['rotation'] = 270;
                $items[$screenIndex]['x'] = $bedFootX - ($items[$screenIndex]['depth'] / 2) - .005;
                $items[$screenIndex]['y'] = $items[$lilyBedIndex]['y'];
                if ($cabinetIndex !== false) {
                    $items[$screenIndex]['base_z'] = $items[$cabinetIndex]['height'] + .05;
                }
            }
        }

        $mirror = $find('193', 'attic');
        if ($mirror !== false) {
            $items[$mirror]['rotation'] = 180;
            $items[$mirror]['y'] = $lilyTop - ($items[$mirror]['depth'] / 2);
        }

        $armchair = $find('259', 'attic');
        if ($armchair !== false) {
            $items[$armchair]['rotation'] = 270;
            $items[$armchair]['x'] = $leftKnee + ($items[$armchair]['depth'] / 2);
            $items[$armchair]['y'] = $lilyBottom + ($items[$armchair]['width'] / 2);
        }

        $sideTable = $find('260', 'attic');
        if ($sideTable !== false) {
            $items[$sideTable]['rotation'] = 270;
            $items[$sideTable]['x'] = $leftKnee + ($items[$sideTable]['depth'] / 2);
            $items[$sideTable]['y'] = min($lilyTop - ($items[$sideTable]['width'] / 2), max($lilyBottom + ($items[$sideTable]['width'] / 2), $items[$sideTable]['y']));
        }

        $desk = $find('263', 'attic');
        if ($desk !== false) {
            $items[$desk]['rotation'] = 270;
            $items[$desk]['x'] = $roomRight - ($items[$desk]['depth'] / 2);
            $items[$desk]['y'] = min($lilyTop - ($items[$desk]['width'] / 2), max($lilyBottom + ($items[$desk]['width'] / 2), $items[$desk]['y']));

            $computer = $find('264', 'attic');
            if ($computer !== false) {
                $items[$computer]['x'] = $items[$desk]['x'];
                $items[$computer]['y'] = $items[$desk]['y'];
            }

            $chair = $find('262', 'attic');
            if ($chair !== false) {
                $items[$chair]['rotation'] = 270;
                $items[$chair]['x'] = $items[$desk]['x'] - ($items[$desk]['depth'] / 2) - ($items[$chair]['depth'] / 2) - .15;
                $items[$chair]['y'] = $items[$desk]['y'];
            }
        }

        $infrared = $find('905', 'attic');
        if ($infrared !== false) {
            $items[$infrared]['rotation'] = 270;
            $items[$infrared]['x'] = $leftKnee + ($items[$infrared]['depth'] / 2);
            $items[$infrared]['y'] = min($lilyTop - ($items[$infrared]['width'] / 2), max($lilyBottom + ($items[$infrared]['width'] / 2), $items[$infrared]['y']));
        }

        // Kledingkast room (1.90 x 2.00 m). Dimensions are never altered here;
        // a saved measurement from the site remains authoritative.
        $zolderTable = $find('269', 'attic');
        if ($zolderTable !== false) {
            $items[$zolderTable]['rotation'] = 180;
            $items[$zolderTable]['x'] = ($leftKnee + $closetRight) / 2;
            $items[$zolderTable]['y'] = $top - ($items[$zolderTable]['depth'] / 2);

            $zolderChair = $find('268', 'attic');
            if ($zolderChair !== false) {
                $items[$zolderChair]['rotation'] = 0;
                $items[$zolderChair]['x'] = $items[$zolderTable]['x'];
                $items[$zolderChair]['y'] = $items[$zolderTable]['y'] - ($items[$zolderTable]['depth'] / 2) - ($items[$zolderChair]['depth'] / 2) - .10;
            }
        }

        $basket = $find('271', 'attic');
        if ($basket !== false) {
            $items[$basket]['rotation'] = 270;
            $items[$basket]['x'] = $leftKnee + ($items[$basket]['depth'] / 2);
            $items[$basket]['y'] = $upperBottom + ($items[$basket]['width'] / 2);
        }

        $wardrobe = $find('903', 'attic');
        if ($wardrobe !== false) {
            $items[$wardrobe]['rotation'] = 90;
            $items[$wardrobe]['x'] = $closetRight - ($items[$wardrobe]['depth'] / 2);
            $items[$wardrobe]['y'] = $top - ($items[$wardrobe]['width'] / 2);
        }

        // Zolder/trap room is 2.30 x 2.00 m. The old stair placeholder was
        // wider than the measured room; only use the room width as a fallback
        // when the stair itself has never been measured on the site.
        $stairs = $find('904', 'attic');
        if ($stairs !== false) {
            if (! isset($overrides['attic:904']['width'])) {
                $items[$stairs]['width'] = 2.30;
            }
            $items[$stairs]['rotation'] = 0;
            $items[$stairs]['x'] = -.15;
            $items[$stairs]['y'] = $top - ($items[$stairs]['depth'] / 2);
        }

        // Washok is 5.50 x 2.20 m between x=1.20 and x=3.40. Keep all
        // appliances against actual room walls and use their saved dimensions.
        $washer1 = $find('272', 'attic');
        if ($washer1 !== false) {
            $items[$washer1]['rotation'] = 90;
            $items[$washer1]['x'] = $washokRight - ($items[$washer1]['depth'] / 2);
            $items[$washer1]['y'] = $top - ($items[$washer1]['width'] / 2);
        }
        $washer2 = $find('273', 'attic');
        if ($washer2 !== false) {
            $items[$washer2]['rotation'] = 90;
            $items[$washer2]['x'] = $washokRight - ($items[$washer2]['depth'] / 2);
            $items[$washer2]['y'] = $washer1 !== false
                ? $items[$washer1]['y'] - ($items[$washer1]['width'] / 2) - ($items[$washer2]['width'] / 2)
                : 1.45;
        }

        $boiler = $find('906', 'attic');
        if ($boiler !== false) {
            $items[$boiler]['x'] = $washokLeft + ($items[$boiler]['width'] / 2);
            $items[$boiler]['y'] = $top - ($items[$boiler]['depth'] / 2);
        }
        $heating = $find('907', 'attic');
        if ($heating !== false) {
            $items[$heating]['y'] = $top - ($items[$heating]['depth'] / 2);
            $items[$heating]['x'] = $boiler !== false
                ? min($washokRight - ($items[$heating]['width'] / 2), $items[$boiler]['x'] + ($items[$boiler]['width'] / 2) + ($items[$heating]['width'] / 2))
                : $washokLeft + ($items[$heating]['width'] / 2);
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
