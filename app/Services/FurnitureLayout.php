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

        // Cabinet + TV for the first-floor landing. Their exact placement
        // against the wall between the two bedroom doors is calculated below.
        $items[] = [
            'id' => '257', 'floor' => 'upper', 'kind' => 'cabinet',
            'x' => -0.0349, 'y' => 0.3992,
            'width' => 0.71, 'depth' => 0.34, 'height' => 0.87,
            'rotation' => 180, 'source_shape' => 'dresser', 'measured' => true,
        ];
        $items[] = [
            'id' => '258', 'floor' => 'upper', 'kind' => 'tv',
            'x' => -0.0349, 'y' => 0.3992,
            'width' => 0.62, 'depth' => 0.10, 'height' => 0.40,
            'rotation' => 180, 'source_shape' => 'flat_tv',
            'base_z' => 0.87, 'standing' => true, 'measured' => true,
        ];
        $items[] = [
            'id' => '256-mirror', 'floor' => 'upper', 'kind' => 'mirror',
            'x' => 2.7297, 'y' => 0.2933,
            'width' => 1.5135, 'depth' => 0.03, 'height' => 0.75,
            'rotation' => 180, 'source_shape' => 'user_specified',
            'base_z' => 0.67, 'measured' => true,
        ];
        $items[] = [
            'id' => '256-radiator', 'floor' => 'upper', 'kind' => 'Wandradiator',
            'model_kind' => 'wall_radiator',
            'x' => 1.9628, 'y' => 1.1687,
            'width' => 0.75, 'depth' => 0.10, 'height' => 0.60,
            'base_z' => 0.15, 'rotation' => 90,
            'source_shape' => 'user_specified', 'measured' => true,
        ];

        // Bijspringer (upper-floor office). Exact placement against the Levi
        // wall is calculated in items() after any saved measurements are applied.
        $items = array_merge($items, [
            ['id' => '259', 'floor' => 'upper', 'kind' => 'Hangend bureaublad', 'model_kind' => 'hanging_desk', 'x' => -2.00, 'y' => 2.35, 'width' => 1.20, 'depth' => 0.80, 'height' => 0.04, 'base_z' => 0.96, 'rotation' => 180, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '260', 'floor' => 'upper', 'kind' => 'Monitor', 'model_kind' => 'monitor', 'x' => -2.27, 'y' => 2.67, 'width' => 0.54, 'depth' => 0.15, 'height' => 0.25, 'base_z' => 1.00, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '261', 'floor' => 'upper', 'kind' => 'Monitor', 'model_kind' => 'monitor', 'x' => -1.73, 'y' => 2.67, 'width' => 0.54, 'depth' => 0.15, 'height' => 0.25, 'base_z' => 1.00, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '262', 'floor' => 'upper', 'kind' => 'Laptop', 'model_kind' => 'laptop', 'x' => -2.00, 'y' => 2.20, 'width' => 0.36, 'depth' => 0.29, 'height' => 0.25, 'base_z' => 1.00, 'rotation' => 180, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '263', 'floor' => 'upper', 'kind' => 'Kledingkast', 'model_kind' => 'wardrobe_drawers', 'x' => -3.30, 'y' => 2.435, 'width' => 1.40, 'depth' => 0.63, 'height' => 1.88, 'rotation' => 180, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '264', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk', 'x' => -3.335, 'y' => .5633, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '265', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk', 'x' => -2.605, 'y' => .5633, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '266', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk', 'x' => -3.335, 'y' => .5633, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75, 'base_z' => 0.75, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '267', 'floor' => 'upper', 'kind' => 'MICKE bureau', 'model_kind' => 'micke_desk', 'x' => -2.605, 'y' => .5633, 'width' => 0.73, 'depth' => 0.57, 'height' => 0.75, 'base_z' => 0.75, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => '268', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4', 'x' => -3.335, 'y' => .5633, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50, 'rotation' => 0, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true],
            ['id' => '269', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4', 'x' => -2.605, 'y' => .5633, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50, 'rotation' => 0, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true],
            ['id' => '270', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4', 'x' => -3.335, 'y' => .5633, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50, 'base_z' => 0.75, 'rotation' => 0, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true],
            ['id' => '271', 'floor' => 'upper', 'kind' => '3D-printer', 'model_kind' => 'kobra4', 'x' => -2.605, 'y' => .5633, 'width' => 0.40, 'depth' => 0.55, 'height' => 0.50, 'base_z' => 0.75, 'rotation' => 0, 'source_shape' => 'Anycubic Kobra 4', 'measured' => true],
            ['id' => 'bijspringer-skadis-1', 'floor' => 'upper', 'kind' => 'SKADIS met filament', 'model_kind' => 'skadis_filament', 'x' => -1.918, 'y' => .2933, 'width' => 0.55, 'depth' => 0.03, 'height' => 0.55, 'base_z' => 0.45, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true],
            ['id' => 'bijspringer-skadis-2', 'floor' => 'upper', 'kind' => 'SKADIS met filament', 'model_kind' => 'skadis_filament', 'x' => -1.918, 'y' => .2933, 'width' => 0.55, 'depth' => 0.03, 'height' => 0.55, 'base_z' => 1.00, 'rotation' => 0, 'source_shape' => 'user_measured', 'measured' => true],
        ]);

        // Attic additions are real furniture items as well, so they are visible
        // in the measurement/editor UI instead of existing only in Blender.
        $items = array_merge($items, [
            ['id' => 'laundry-basket', 'floor' => 'attic', 'kind' => 'Wasmand', 'model_kind' => 'laundry_basket', 'x' => -1.65, 'y' => .90, 'width' => .30, 'depth' => .30, 'height' => .80, 'rotation' => 0, 'source_shape' => 'user_specified', 'measured' => true],
            ['id' => 'washok-rack-right', 'floor' => 'attic', 'kind' => 'Stellingkast washok rechts', 'model_kind' => 'storage_rack', 'x' => 3.10, 'y' => -.915, 'width' => 3.67, 'depth' => .60, 'height' => 1.80, 'rotation' => 90, 'source_shape' => 'user_specified', 'measured' => true],
            ['id' => 'washok-rack-bottom', 'floor' => 'attic', 'kind' => 'Stellingkast washok onder', 'model_kind' => 'storage_rack', 'x' => 2.30, 'y' => -2.45, 'width' => 1.00, 'depth' => .60, 'height' => 1.80, 'rotation' => 0, 'source_shape' => 'user_specified', 'measured' => true],
            ['id' => 'washok-rack-left', 'floor' => 'attic', 'kind' => 'Stellingkast washok links', 'model_kind' => 'storage_rack', 'x' => 1.50, 'y' => -.975, 'width' => 3.55, 'depth' => .60, 'height' => 1.80, 'rotation' => 90, 'source_shape' => 'user_specified', 'measured' => true],
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
        // only changes placement/rotation, so freshly measured dimensions remain authoritative.
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

        // V1-239 stays between V1-236 and V1-220, with its headboard flush
        // against the outside/headboard wall.
        $bedIndex = $find('239', 'upper');
        $leftBedCabinet = $find('236', 'upper');
        $rightBedCabinet = $find('220', 'upper');
        if ($bedIndex !== false) {
            $items[$bedIndex]['rotation'] = 0;
            $items[$bedIndex]['y'] = -2.75 + ($items[$bedIndex]['depth'] / 2);
            if ($leftBedCabinet !== false && $rightBedCabinet !== false) {
                $leftInner = $items[$leftBedCabinet]['x'] + ($items[$leftBedCabinet]['width'] / 2);
                $rightInner = $items[$rightBedCabinet]['x'] - ($items[$rightBedCabinet]['width'] / 2);
                $items[$bedIndex]['x'] = ($leftInner + $rightInner) / 2;
            }
        }

        // V1-219: rotate 45 degrees counter-clockwise from its original 180°.
        $mirrorCabinet = $find('219', 'upper');
        if ($mirrorCabinet !== false) {
            $items[$mirrorCabinet]['rotation'] = 225;
        }

        // Slaapkamer: V1-244 is flush against the partition wall toward Levi.
        // V1-901 is the legacy levi-tv-cabinet display alias and sits 5 cm from
        // the foot of the bed. V1-253 follows the cabinet and stands on top.
        $sleepingBed = $find('244', 'upper');
        $footCabinet = $find('levi-tv-cabinet', 'upper');
        $footTv = $find('253', 'upper');
        if ($sleepingBed !== false) {
            $bedroomRight = -.1337;
            $bedroomBottom = -2.75;
            $bedroomTop = .1855;
            $items[$sleepingBed]['rotation'] = 90;
            $items[$sleepingBed]['x'] = $bedroomRight - ($items[$sleepingBed]['depth'] / 2);
            $items[$sleepingBed]['y'] = min(
                $bedroomTop - ($items[$sleepingBed]['width'] / 2),
                max($bedroomBottom + ($items[$sleepingBed]['width'] / 2), $items[$sleepingBed]['y']),
            );
            $bedFootX = $items[$sleepingBed]['x'] - ($items[$sleepingBed]['depth'] / 2);

            if ($footCabinet !== false) {
                $items[$footCabinet]['rotation'] = 270;
                $items[$footCabinet]['x'] = $bedFootX - .05 - ($items[$footCabinet]['depth'] / 2);
                $items[$footCabinet]['y'] = $items[$sleepingBed]['y'];
            }
            if ($footTv !== false && $footCabinet !== false) {
                $items[$footTv]['rotation'] = 270;
                $items[$footTv]['x'] = $items[$footCabinet]['x'];
                $items[$footTv]['y'] = $items[$footCabinet]['y'];
                $items[$footTv]['base_z'] = $items[$footCabinet]['height'];
            }
        }

        // Eerste verdieping: V1-257 sits against the 63 cm wall between the two
        // bedroom doors: the wall bordering both the slaapkamer and Levi's room.
        $landingCabinet = $find('257', 'upper');
        $landingTv = $find('258', 'upper');
        if ($landingCabinet !== false) {
            $betweenBedroomsLeft = -.3499;
            $betweenBedroomsRight = .2801;
            $bedroomDoorWallY = .2292;
            $items[$landingCabinet]['rotation'] = 180;
            $items[$landingCabinet]['x'] = ($betweenBedroomsLeft + $betweenBedroomsRight) / 2;
            $items[$landingCabinet]['y'] = $bedroomDoorWallY + ($items[$landingCabinet]['depth'] / 2);

            if ($landingTv !== false) {
                $items[$landingTv]['rotation'] = 180;
                $items[$landingTv]['x'] = $items[$landingCabinet]['x'];
                $items[$landingTv]['y'] = $items[$landingCabinet]['y'];
                $items[$landingTv]['base_z'] = $items[$landingCabinet]['height'];
            }
        }

        // Badkamer: V1-256 remains against the lower wall and is shifted 20 cm
        // left on the furniture map. The mirror follows it. The 75 cm radiator
        // is immediately beside V1-256 around the left-hand corner, not near V1-254.
        $bathSink = $find('256', 'upper');
        $bathMirror = $find('256-mirror', 'upper');
        $bathRadiator = $find('256-radiator', 'upper');
        if ($bathSink !== false) {
            $bathroomBottom = .2783;
            $bathroomLeft = 1.9128;
            $items[$bathSink]['rotation'] = 180;
            $items[$bathSink]['x'] += .20;
            $items[$bathSink]['y'] = $bathroomBottom + ($items[$bathSink]['depth'] / 2);

            if ($bathMirror !== false) {
                $items[$bathMirror]['rotation'] = 180;
                $items[$bathMirror]['x'] = $items[$bathSink]['x'];
                $items[$bathMirror]['y'] = $bathroomBottom + ($items[$bathMirror]['depth'] / 2);
                $items[$bathMirror]['width'] = $items[$bathSink]['width'];
                $items[$bathMirror]['base_z'] = $items[$bathSink]['height'] + .10;
            }

            if ($bathRadiator !== false) {
                $items[$bathRadiator]['rotation'] = 90;
                $items[$bathRadiator]['x'] = $bathroomLeft + ($items[$bathRadiator]['depth'] / 2);
                $items[$bathRadiator]['y'] = $bathroomBottom + $items[$bathSink]['depth'] + .05 + ($items[$bathRadiator]['width'] / 2);
            }
        }

        // Bijspringer: two MICKE stacks remain against the wall shared with Levi,
        // but the entire run starts 30 cm away from the outside wall.
        $officeBottom = .2783;
        $officeLeft = -4.0;
        $officeRight = -1.343;
        $desk264 = $find('264', 'upper');
        $desk265 = $find('265', 'upper');
        $desk266 = $find('266', 'upper');
        $desk267 = $find('267', 'upper');

        if ($desk264 !== false && $desk265 !== false) {
            $leftDeskX = $officeLeft + .30 + ($items[$desk264]['width'] / 2);
            $rightDeskX = $leftDeskX + ($items[$desk264]['width'] / 2) + ($items[$desk265]['width'] / 2);

            foreach ([[$desk264, $leftDeskX], [$desk266, $leftDeskX], [$desk265, $rightDeskX], [$desk267, $rightDeskX]] as [$index, $x]) {
                if ($index === false) {
                    continue;
                }
                $items[$index]['rotation'] = 0;
                $items[$index]['x'] = $x;
                $items[$index]['y'] = $officeBottom + ($items[$index]['depth'] / 2);
            }

            foreach ([['268', $desk264], ['270', $desk266], ['269', $desk265], ['271', $desk267]] as [$printerId, $deskIndex]) {
                $printerIndex = $find($printerId, 'upper');
                if ($printerIndex === false || $deskIndex === false) {
                    continue;
                }
                $items[$printerIndex]['rotation'] = 0;
                $items[$printerIndex]['x'] = $items[$deskIndex]['x'];
                $items[$printerIndex]['y'] = $items[$deskIndex]['y'];
            }
        }

        // Both SKADIS boards move 20 cm toward the outside wall (away from the
        // door) and remain vertically one below the other on the Levi wall.
        // Their mounting heights come from the baseline/DB override and are not
        // overwritten here, so the furniture editor can persist base_z changes.
        $skadis1 = $find('bijspringer-skadis-1', 'upper');
        $skadis2 = $find('bijspringer-skadis-2', 'upper');
        if ($skadis1 !== false) {
            $items[$skadis1]['rotation'] = 0;
            $items[$skadis1]['x'] = $officeRight - .10 - .20 - ($items[$skadis1]['width'] / 2);
            $items[$skadis1]['y'] = $officeBottom + ($items[$skadis1]['depth'] / 2);
        }
        if ($skadis2 !== false) {
            $items[$skadis2]['rotation'] = 0;
            $items[$skadis2]['x'] = $skadis1 !== false ? $items[$skadis1]['x'] : $officeRight - .10 - .20 - ($items[$skadis2]['width'] / 2);
            $items[$skadis2]['y'] = $officeBottom + ($items[$skadis2]['depth'] / 2);
        }

        // Final attic clear-wall coordinates.
        $leftKnee = -3.40;
        $roomRight = 1.00;
        $washokLeft = 1.20;
        $washokRight = 3.40;
        $lilyBottom = -2.75;
        $lilyTop = .55;
        $upperBottom = .75;
        $top = 2.75;
        $closetRight = -1.50;

        $lilyBedIndex = $find('191', 'attic');
        if ($lilyBedIndex !== false) {
            $items[$lilyBedIndex]['rotation'] = 90;
            $items[$lilyBedIndex]['x'] = $roomRight - ($items[$lilyBedIndex]['depth'] / 2);
            $items[$lilyBedIndex]['y'] = $lilyBottom + ($items[$lilyBedIndex]['width'] / 2);
            $bedFootX = $items[$lilyBedIndex]['x'] - ($items[$lilyBedIndex]['depth'] / 2);

            $cabinetIndex = $find('261', 'attic');
            if ($cabinetIndex !== false) {
                $items[$cabinetIndex]['rotation'] = 90;
                // 5 cm to the visual right, while keeping the original rotation.
                $items[$cabinetIndex]['x'] = $bedFootX - ($items[$cabinetIndex]['depth'] / 2) - .05;
                $items[$cabinetIndex]['y'] = $items[$lilyBedIndex]['y'];
            }

            $screenIndex = $find('201', 'attic');
            if ($screenIndex !== false) {
                $items[$screenIndex]['rotation'] = 270;
                // 50 cm to the visual right. On this mirrored plan that is negative X.
                $items[$screenIndex]['x'] = $bedFootX - ($items[$screenIndex]['depth'] / 2) - .505;
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

        // Z-260 belongs in the upper-left corner of Lily: against both walls.
        $sideTable = $find('260', 'attic');
        if ($sideTable !== false) {
            $items[$sideTable]['rotation'] = 270;
            $items[$sideTable]['x'] = $leftKnee + ($items[$sideTable]['depth'] / 2);
            $items[$sideTable]['y'] = $lilyTop - ($items[$sideTable]['width'] / 2);
        }

        // Z-263 belongs in the upper-right corner of Lily: against both walls.
        $desk = $find('263', 'attic');
        if ($desk !== false) {
            $items[$desk]['rotation'] = 270;
            $items[$desk]['x'] = $roomRight - ($items[$desk]['depth'] / 2);
            $items[$desk]['y'] = $lilyTop - ($items[$desk]['width'] / 2);

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

        // Z-269 keeps its original 180-degree rotation. Only move it against
        // the schot/right wall; do not rotate it as part of the placement.
        $zolderTable = $find('269', 'attic');
        if ($zolderTable !== false) {
            $items[$zolderTable]['rotation'] = 180;
            $items[$zolderTable]['x'] = $leftKnee + ($items[$zolderTable]['width'] / 2);
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

        $laundryBasket = $find('laundry-basket', 'attic');
        if ($laundryBasket !== false && $wardrobe !== false) {
            $wardrobeBottom = $items[$wardrobe]['y'] - ($items[$wardrobe]['width'] / 2);
            $items[$laundryBasket]['x'] = $closetRight - ($items[$laundryBasket]['depth'] / 2);
            $items[$laundryBasket]['y'] = max(
                $upperBottom + ($items[$laundryBasket]['width'] / 2),
                $wardrobeBottom - ($items[$laundryBasket]['width'] / 2),
            );
        }

        $stairs = $find('904', 'attic');
        if ($stairs !== false) {
            if (! isset($overrides['attic:904']['width'])) {
                $items[$stairs]['width'] = 2.30;
            }
            $items[$stairs]['rotation'] = 0;
            $items[$stairs]['x'] = -.15;
            $items[$stairs]['y'] = $top - ($items[$stairs]['depth'] / 2);
        }

        // Washok appliances against the right/top walls.
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

        // Editable washok racks. They form one run: 10 cm after Z-273, around
        // the bottom corners, and end 10 cm before the doorway at y=.90.
        $bottom = -2.75;
        $doorStop = .80;
        $rackStartY = $washer2 !== false
            ? $items[$washer2]['y'] - ($items[$washer2]['width'] / 2) - .10
            : .80;

        $rackRight = $find('washok-rack-right', 'attic');
        $rackBottom = $find('washok-rack-bottom', 'attic');
        $rackLeft = $find('washok-rack-left', 'attic');

        if ($rackRight !== false) {
            if (! isset($overrides['attic:washok-rack-right']['width'])) {
                $items[$rackRight]['width'] = max(.20, $rackStartY - $bottom);
            }
            $items[$rackRight]['rotation'] = 90;
            $items[$rackRight]['x'] = $washokRight - ($items[$rackRight]['depth'] / 2);
            $items[$rackRight]['y'] = $bottom + ($items[$rackRight]['width'] / 2);
        }

        if ($rackLeft !== false) {
            if (! isset($overrides['attic:washok-rack-left']['width'])) {
                $items[$rackLeft]['width'] = max(.20, $doorStop - $bottom);
            }
            $items[$rackLeft]['rotation'] = 90;
            $items[$rackLeft]['x'] = $washokLeft + ($items[$rackLeft]['depth'] / 2);
            $items[$rackLeft]['y'] = $bottom + ($items[$rackLeft]['width'] / 2);
        }

        if ($rackBottom !== false) {
            $leftDepth = $rackLeft !== false ? $items[$rackLeft]['depth'] : .60;
            $rightDepth = $rackRight !== false ? $items[$rackRight]['depth'] : .60;
            $innerLeft = $washokLeft + $leftDepth;
            $innerRight = $washokRight - $rightDepth;
            if (! isset($overrides['attic:washok-rack-bottom']['width'])) {
                $items[$rackBottom]['width'] = max(.20, $innerRight - $innerLeft);
            }
            $items[$rackBottom]['rotation'] = 0;
            $items[$rackBottom]['x'] = ($innerLeft + $innerRight) / 2;
            $items[$rackBottom]['y'] = $bottom + ($items[$rackBottom]['depth'] / 2);
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
