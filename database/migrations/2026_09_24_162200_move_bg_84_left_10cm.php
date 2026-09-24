<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $item = DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '84')
                ->first();

            if (! $item) {
                throw new RuntimeException('BG-84 is required for this migration.');
            }

            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '84')
                ->update([
                    'x' => round((float) $item->x - 0.10, 5),
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
                ->where('item_id', '84')
                ->update([
                    'x' => DB::raw('x + 0.10'),
                ]);

            DB::table('furniture_layouts')->where('id', 1)->update([
                'revision' => DB::raw('revision + 1'),
                'updated_at' => now(),
            ]);
        });
    }
};
