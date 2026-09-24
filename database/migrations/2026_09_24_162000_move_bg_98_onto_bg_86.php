<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $plant = DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '98')
                ->first();
            $cabinet = DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '86')
                ->first();
            $sofa = DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '73')
                ->first();

            if (! $plant || ! $cabinet || ! $sofa) {
                throw new RuntimeException('BG-98, BG-86 and BG-73 are required for this migration.');
            }

            $cabinetRight = (float) $cabinet->x + ((float) $cabinet->width / 2);
            $sofaLeft = (float) $sofa->x - ((float) $sofa->width / 2);

            if (abs($cabinetRight - $sofaLeft) > 0.02) {
                throw new RuntimeException('BG-86 and BG-73 no longer meet at the expected edge.');
            }

            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '98')
                ->update([
                    // Keep the plant fully against the sofa while it sits on BG-86.
                    'x' => round($cabinetRight - ((float) $plant->width / 2), 5),
                    'y' => round((float) $cabinet->y, 5),
                    'base_z' => round((float) $cabinet->base_z + (float) $cabinet->height, 5),
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
                ->where('item_id', '98')
                ->update([
                    'x' => -2.59430,
                    'y' => -1.69230,
                    'base_z' => 0,
                ]);

            DB::table('furniture_layouts')->where('id', 1)->update([
                'revision' => DB::raw('revision + 1'),
                'updated_at' => now(),
            ]);
        });
    }
};
