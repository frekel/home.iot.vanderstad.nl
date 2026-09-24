<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $sofa = DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '73')
                ->first();
            $table = DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '105')
                ->first();
            $outerWall = DB::table('house_walls')
                ->where('floor_id', 'ground')
                ->where('name', 'outer-01')
                ->first();

            if (! $sofa || ! $table || ! $outerWall) {
                throw new RuntimeException('BG-73, BG-105 and outer-01 are required for this migration.');
            }

            // BG-105 is rotated 90 degrees. Its effective X footprint is its depth,
            // and its effective Y footprint is its width.
            $sofaRight = (float) $sofa->x + ((float) $sofa->width / 2);
            $tableHalfX = (float) $table->depth / 2;
            $tableHalfY = (float) $table->width / 2;
            $outerWallY = min((float) $outerWall->y1, (float) $outerWall->y2);

            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '105')
                ->update([
                    'rotation' => 90,
                    'x' => round($sofaRight + $tableHalfX, 5),
                    'y' => round($outerWallY + $tableHalfY, 5),
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
            $sofa = DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '73')
                ->first();
            $outerWall = DB::table('house_walls')
                ->where('floor_id', 'ground')
                ->where('name', 'outer-01')
                ->first();

            if (! $sofa || ! $outerWall) {
                throw new RuntimeException('BG-73 and outer-01 are required to revert this migration.');
            }

            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '105')
                ->update([
                    'x' => round((float) $sofa->x + ((float) $sofa->width / 2) + .45, 5),
                    'y' => round((float) $outerWall->y1 + .13, 5),
                    'rotation' => 90,
                ]);

            DB::table('furniture_layouts')->where('id', 1)->update([
                'revision' => DB::raw('revision + 1'),
                'updated_at' => now(),
            ]);
        });
    }
};
