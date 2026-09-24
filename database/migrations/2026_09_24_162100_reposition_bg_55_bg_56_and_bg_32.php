<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            foreach (['32', '55', '56'] as $id) {
                $exists = DB::table('furniture_items')
                    ->where('floor_id', 'ground')
                    ->where('item_id', $id)
                    ->exists();

                if (! $exists) {
                    throw new RuntimeException("BG-{$id} is required for this migration.");
                }
            }

            // BG-56 moves 10 cm left; BG-55 moves 20 cm left so the two cabinets meet.
            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '56')
                ->update(['x' => DB::raw('x - 0.10')]);

            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '55')
                ->update(['x' => DB::raw('x - 0.20')]);

            // BG-32: against the toilet's left wall (toward the living room),
            // centered along that wall segment.
            $toilet = DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '32')
                ->first();
            $wall = DB::table('house_walls')
                ->where('floor_id', 'ground')
                ->where('name', 'inner-19')
                ->first();

            if (! $wall) {
                throw new RuntimeException('Ground-floor toilet left wall inner-19 is required for this migration.');
            }

            $angle = deg2rad((float) $toilet->rotation);
            $halfX = (
                abs(cos($angle)) * (float) $toilet->width
                + abs(sin($angle)) * (float) $toilet->depth
            ) / 2;

            $wallX = ((float) $wall->x1 + (float) $wall->x2) / 2;
            $wallMidY = ((float) $wall->y1 + (float) $wall->y2) / 2;
            $insideFaceX = $wallX + ((float) $wall->thickness / 2);

            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '32')
                ->update([
                    'x' => round($insideFaceX + $halfX, 5),
                    'y' => round($wallMidY, 5),
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
                ->where('item_id', '56')
                ->update(['x' => DB::raw('x + 0.10')]);

            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '55')
                ->update(['x' => DB::raw('x + 0.20')]);

            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '32')
                ->update([
                    'x' => 3.63210,
                    'y' => 2.43270,
                ]);

            DB::table('furniture_layouts')->where('id', 1)->update([
                'revision' => DB::raw('revision + 1'),
                'updated_at' => now(),
            ]);
        });
    }
};