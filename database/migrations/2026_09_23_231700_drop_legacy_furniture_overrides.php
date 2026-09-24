<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('furniture_layouts', function (Blueprint $table) {
            $table->dropColumn('overrides');
        });
    }

    public function down(): void
    {
        Schema::table('furniture_layouts', function (Blueprint $table) {
            $table->json('overrides')->nullable();
        });

        if (! Schema::hasTable('furniture_items')) {
            DB::table('furniture_layouts')->where('id', 1)->update(['overrides' => '{}']);
            return;
        }

        $stored = [];
        foreach (DB::table('furniture_items')->get() as $item) {
            $stored[$item->floor_id.':'.$item->item_id] = [
                'x' => (float) $item->x,
                'y' => (float) $item->y,
                'rotation' => (float) $item->rotation,
                'width' => (float) $item->width,
                'depth' => (float) $item->depth,
                'height' => (float) $item->height,
                'base_z' => (float) $item->base_z,
            ];
        }

        DB::table('furniture_layouts')->where('id', 1)->update([
            'overrides' => json_encode($stored, JSON_THROW_ON_ERROR),
        ]);
    }
};
