<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $ids = ['105', '106', '200', '201', '202', '203', '204', '205', '206'];

            $existing = DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->whereIn('item_id', $ids)
                ->pluck('item_id')
                ->all();

            if ($existing !== []) {
                throw new RuntimeException('Ground-floor furniture already exists: '.implode(', ', $existing));
            }

            $sofa = DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '73')
                ->first();

            if (! $sofa) {
                throw new RuntimeException('BG-73 is required for this migration.');
            }

            $sofaMetadata = $sofa->metadata
                ? json_decode($sofa->metadata, true, flags: JSON_THROW_ON_ERROR)
                : [];
            $seatDepth = (float) ($sofaMetadata['seat_depth'] ?? 0);

            if ($seatDepth <= 0) {
                throw new RuntimeException('BG-73 seat_depth metadata is required for BG-106 placement.');
            }

            $topWall = DB::table('house_walls')
                ->where('floor_id', 'ground')
                ->where('name', 'outer-01')
                ->first();
            $hallWall = DB::table('house_walls')
                ->where('floor_id', 'ground')
                ->where('name', 'inner-11')
                ->first();

            if (! $topWall || ! $hallWall) {
                throw new RuntimeException('Ground-floor wall geometry is required for this migration.');
            }

            $sofaRight = (float) $sofa->x + ((float) $sofa->width / 2);
            $sofaFront = (float) $sofa->y - ((float) $sofa->depth / 2) + $seatDepth;
            $topWallY = (float) $topWall->y1;

            // Hall row against inner-11, from left to right: BG-200, 201, 202, 203, 205.
            // The original requested 50 cm offset does not fit. Use the remaining
            // space at the left so BG-205 ends exactly at the right end of the wall.
            $hallLeft = min((float) $hallWall->x1, (float) $hallWall->x2);
            $hallRight = max((float) $hallWall->x1, (float) $hallWall->x2);
            $hallWallY = (float) $hallWall->y1;
            $rowWidths = [
                '200' => .35,
                '201' => .46,
                '202' => .46,
                '203' => 1.70,
                '205' => .80,
            ];
            $rowWidth = array_sum($rowWidths);
            $available = $hallRight - $hallLeft;

            if ($rowWidth > $available) {
                throw new RuntimeException('BG-200 through BG-205 do not fit on the hallway wall.');
            }

            $cursor = $hallLeft + ($available - $rowWidth);
            $hallX = [];
            foreach ($rowWidths as $id => $width) {
                $hallX[$id] = $cursor + ($width / 2);
                $cursor += $width;
            }

            $sortOrder = ((int) DB::table('furniture_items')->max('sort_order')) + 1;
            $items = [
                [
                    'item_id' => '105',
                    'kind' => 'table',
                    'x' => $sofaRight + .45,
                    'y' => $topWallY + .13,
                    'rotation' => 0,
                    'width' => .90,
                    'depth' => .26,
                    'height' => .75,
                    'base_z' => 0,
                    'metadata' => ['measured' => true, 'note' => 'Smalle side table tegen de bovenste buitenwand, direct tegen BG-73.'],
                ],
                [
                    'item_id' => '106',
                    'kind' => 'table',
                    'x' => $sofaRight - .275,
                    'y' => $sofaFront + .275,
                    'rotation' => 0,
                    'width' => .55,
                    'depth' => .55,
                    'height' => .45,
                    'base_z' => 0,
                    'metadata' => ['measured' => true, 'note' => 'Tafeltje tegen de voorkant rechts van BG-73.'],
                ],
                [
                    'item_id' => '200',
                    'kind' => 'drawer_cabinet',
                    'x' => $hallX['200'],
                    'y' => $hallWallY + .13,
                    'rotation' => 0,
                    'width' => .35,
                    'depth' => .26,
                    'height' => 1.32,
                    'base_z' => 0,
                    'metadata' => ['measured' => true, 'note' => 'Smalle hoge ladekast met kleine laden; start zo dicht mogelijk bij de linkerwand zodat de volledige rij past.'],
                ],
                [
                    'item_id' => '201',
                    'kind' => 'drawer_cabinet',
                    'x' => $hallX['201'],
                    'y' => $hallWallY + .15,
                    'rotation' => 0,
                    'width' => .46,
                    'depth' => .30,
                    'height' => 1.46,
                    'base_z' => 0,
                    'metadata' => ['measured' => true, 'note' => 'Ladekast direct tegen BG-200.'],
                ],
                [
                    'item_id' => '202',
                    'kind' => 'drawer_cabinet',
                    'x' => $hallX['202'],
                    'y' => $hallWallY + .15,
                    'rotation' => 0,
                    'width' => .46,
                    'depth' => .30,
                    'height' => 1.80,
                    'base_z' => 0,
                    'metadata' => ['measured' => true, 'note' => 'Ladekast direct tegen BG-201.'],
                ],
                [
                    'item_id' => '203',
                    'kind' => 'open_shelving',
                    'x' => $hallX['203'],
                    'y' => $hallWallY + .20,
                    'rotation' => 0,
                    'width' => 1.70,
                    'depth' => .40,
                    'height' => .82,
                    'base_z' => 0,
                    'metadata' => ['measured' => true, 'note' => 'Open kast direct tegen BG-202.'],
                ],
                [
                    'item_id' => '204',
                    'kind' => 'wall_shelf',
                    'x' => $hallX['203'],
                    'y' => $hallWallY + .31,
                    'rotation' => 0,
                    'width' => 1.70,
                    'depth' => .62,
                    'height' => .03,
                    'base_z' => .90,
                    'metadata' => ['measured' => true, 'city_scenery' => true, 'note' => 'Hangende plank boven BG-203 met stadsscenery; onderkant op 90 cm.'],
                ],
                [
                    'item_id' => '205',
                    'kind' => 'shoe_rack',
                    'x' => $hallX['205'],
                    'y' => $hallWallY + .20,
                    'rotation' => 0,
                    'width' => .80,
                    'depth' => .40,
                    'height' => .90,
                    'base_z' => 0,
                    'metadata' => ['measured' => true, 'note' => 'Schoenenrek direct naast BG-203.'],
                ],
                [
                    'item_id' => '206',
                    'kind' => 'wall_shelf',
                    'x' => $hallX['205'],
                    'y' => $hallWallY + .20,
                    'rotation' => 0,
                    'width' => .80,
                    'depth' => .40,
                    'height' => .30,
                    'base_z' => 1.90,
                    'metadata' => ['measured' => true, 'coat_rack' => true, 'note' => 'Kapstok boven BG-205; bovenkant exact op 220 cm.'],
                ],
            ];

            foreach ($items as $item) {
                $metadata = $item['metadata'];
                unset($item['metadata']);

                DB::table('furniture_items')->insert([
                    'floor_id' => 'ground',
                    ...$item,
                    'model_kind' => null,
                    'source_shape' => 'user_measured',
                    'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                    'sort_order' => $sortOrder++,
                ]);
            }

            DB::table('furniture_layouts')->where('id', 1)->update([
                'revision' => DB::raw('revision + 1'),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->whereIn('item_id', ['105', '106', '200', '201', '202', '203', '204', '205', '206'])
                ->delete();

            DB::table('furniture_layouts')->where('id', 1)->update([
                'revision' => DB::raw('revision + 1'),
                'updated_at' => now(),
            ]);
        });
    }
};
