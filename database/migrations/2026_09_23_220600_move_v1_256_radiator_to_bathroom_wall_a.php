<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $row = DB::table('furniture_layouts')->lockForUpdate()->find(1);
            if (! $row) {
                throw new RuntimeException('Furniture layout row not found.');
            }

            $stored = json_decode($row->overrides, true, flags: JSON_THROW_ON_ERROR);
            $sinkKey = 'upper:256';
            $radiatorKey = 'upper:256-radiator';

            foreach ([$sinkKey, $radiatorKey] as $key) {
                if (! isset($stored[$key])) {
                    throw new RuntimeException("Missing database furniture state for {$key}.");
                }
            }

            foreach (['x', 'width'] as $field) {
                if (! array_key_exists($field, $stored[$sinkKey])) {
                    throw new RuntimeException("Missing {$field} for {$sinkKey} in the database.");
                }
            }
            foreach (['width', 'depth'] as $field) {
                if (! array_key_exists($field, $stored[$radiatorKey])) {
                    throw new RuntimeException("Missing {$field} for {$radiatorKey} in the database.");
                }
            }

            // Bathroom wall A is the horizontal wall bordering the bedroom.
            // Keep the radiator directly left of V1-256 on the furniture map,
            // but place its own back edge flush against wall A.
            $wallAY = 0.2783;
            $stored[$radiatorKey]['x'] = round(
                (float) $stored[$sinkKey]['x']
                + ((float) $stored[$sinkKey]['width'] / 2)
                + ((float) $stored[$radiatorKey]['width'] / 2),
                5,
            );
            $stored[$radiatorKey]['y'] = round(
                $wallAY + ((float) $stored[$radiatorKey]['depth'] / 2),
                5,
            );
            $stored[$radiatorKey]['rotation'] = 180.0;

            DB::table('furniture_layouts')->where('id', 1)->update([
                'revision' => (int) $row->revision + 1,
                'overrides' => json_encode($stored, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $row = DB::table('furniture_layouts')->lockForUpdate()->find(1);
            if (! $row) {
                return;
            }

            $stored = json_decode($row->overrides, true, flags: JSON_THROW_ON_ERROR);
            $sinkKey = 'upper:256';
            $radiatorKey = 'upper:256-radiator';
            if (! isset($stored[$sinkKey], $stored[$radiatorKey])) {
                return;
            }

            // Restore the pre-migration relation created by revision 79.
            $stored[$radiatorKey]['x'] = round(
                (float) $stored[$sinkKey]['x']
                + ((float) $stored[$sinkKey]['width'] / 2)
                + ((float) $stored[$radiatorKey]['width'] / 2),
                5,
            );
            $stored[$radiatorKey]['y'] = round((float) $stored[$sinkKey]['y'], 5);
            $stored[$radiatorKey]['rotation'] = round((float) $stored[$sinkKey]['rotation'], 5);

            DB::table('furniture_layouts')->where('id', 1)->update([
                'revision' => (int) $row->revision + 1,
                'overrides' => json_encode($stored, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });
    }
};
