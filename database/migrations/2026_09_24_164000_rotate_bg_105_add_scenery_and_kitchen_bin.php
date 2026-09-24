<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $required = DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->whereIn('item_id', ['45', '105', '204'])
                ->get()
                ->keyBy('item_id');

            foreach (['45', '105', '204'] as $id) {
                if (! $required->has($id)) {
                    throw new RuntimeException("BG-{$id} is required for this migration.");
                }
            }

            $existing = DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->whereIn('item_id', ['207', '208'])
                ->pluck('item_id')
                ->all();

            if ($existing !== []) {
                throw new RuntimeException('Ground-floor furniture already exists: '.implode(', ', $existing));
            }

            $bg45 = $required->get('45');
            $floor = DB::table('house_floors')->where('floor_id', 'ground')->first();
            $topWall = DB::table('house_walls')
                ->where('floor_id', 'ground')
                ->where('name', 'outer-01')
                ->first();

            if (! $floor || ! $topWall) {
                throw new RuntimeException('Ground-floor geometry is required for BG-207/BG-208 placement.');
            }

            $angle = deg2rad((float) $bg45->rotation);
            $halfX = abs(cos($angle)) * ((float) $bg45->width / 2)
                + abs(sin($angle)) * ((float) $bg45->depth / 2);
            $bg45Right = (float) $bg45->x + $halfX;
            $rightWallX = (float) $floor->length / 2;
            $counterWidth = round($rightWallX - $bg45Right, 5);

            if ($counterWidth < .30) {
                throw new RuntimeException('There is not enough room between BG-45 and the right wall for BG-207.');
            }

            $counterX = $bg45Right + ($counterWidth / 2);
            $trashBinX = $bg45Right + .15;
            $topWallY = (float) $topWall->y1;
            $sortOrder = ((int) DB::table('furniture_items')->max('sort_order')) + 1;

            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '105')
                ->update(['rotation' => 90]);

            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '204')
                ->update(['model_kind' => 'city_scenery_shelf']);

            DB::table('furniture_items')->insert([
                'floor_id' => 'ground',
                'item_id' => '207',
                'kind' => 'trash_bin',
                'model_kind' => 'trash_bin',
                'source_shape' => 'user_measured',
                'x' => $trashBinX,
                'y' => $topWallY + .15,
                'rotation' => 0,
                'width' => .30,
                'depth' => .30,
                'height' => .70,
                'base_z' => 0,
                'metadata' => json_encode([
                    'measured' => true,
                    'note' => 'Hoge prullenbak direct rechts naast BG-45, onder BG-208.',
                ], JSON_THROW_ON_ERROR),
                'sort_order' => $sortOrder++,
            ]);

            DB::table('furniture_items')->insert([
                'floor_id' => 'ground',
                'item_id' => '208',
                'kind' => 'countertop',
                'model_kind' => 'countertop',
                'source_shape' => 'user_measured',
                'x' => $counterX,
                'y' => $topWallY + .30,
                'rotation' => 0,
                'width' => $counterWidth,
                'depth' => .60,
                'height' => .04,
                'base_z' => .86,
                'metadata' => json_encode([
                    'measured' => true,
                    'top_height' => .90,
                    'note' => 'Keukenblad van BG-45 tot de rechterwand; 60 cm diep, bovenkant op 90 cm.',
                ], JSON_THROW_ON_ERROR),
                'sort_order' => $sortOrder,
            ]);

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
                ->whereIn('item_id', ['207', '208'])
                ->delete();

            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '105')
                ->update(['rotation' => 0]);

            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '204')
                ->update(['model_kind' => null]);

            DB::table('furniture_layouts')->where('id', 1)->update([
                'revision' => DB::raw('revision + 1'),
                'updated_at' => now(),
            ]);
        });
    }
};
