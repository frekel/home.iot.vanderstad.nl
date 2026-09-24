<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $get = function (string $id) {
                $item = DB::table('furniture_items')
                    ->where('floor_id', 'ground')
                    ->where('item_id', $id)
                    ->first();

                if (! $item) {
                    throw new RuntimeException("BG-{$id} is required for this migration.");
                }

                return $item;
            };

            $halfExtents = function ($item): array {
                $angle = deg2rad((float) $item->rotation);
                $cos = abs(cos($angle));
                $sin = abs(sin($angle));
                $width = (float) $item->width;
                $depth = (float) $item->depth;

                return [
                    ($cos * $width + $sin * $depth) / 2,
                    ($sin * $width + $cos * $depth) / 2,
                ];
            };

            $floor = DB::table('house_floors')->where('id', 'ground')->first();
            if (! $floor) {
                throw new RuntimeException('Ground floor geometry is required for this migration.');
            }

            $sofa = $get('73');
            $bg83 = $get('83');
            $bg84 = $get('84');
            $bg86 = $get('86');
            $bg102 = $get('102');
            $bg45 = $get('45');

            [$sofaHalfX] = $halfExtents($sofa);
            [$bg83HalfX, $bg83HalfY] = $halfExtents($bg83);
            [$bg84HalfX] = $halfExtents($bg84);
            [$bg86HalfX, $bg86HalfY] = $halfExtents($bg86);
            [, $bg102HalfY] = $halfExtents($bg102);
            [, $bg45HalfY] = $halfExtents($bg45);

            // BG-86: place its right edge exactly against BG-73.
            $sofaLeft = (float) $sofa->x - $sofaHalfX;
            $bg86X = $sofaLeft - $bg86HalfX;
            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '86')
                ->update(['x' => round($bg86X, 5)]);

            // BG-83: fit into the corner made by BG-73 and BG-86.
            // Its right edge touches the sofa; its upper edge touches BG-86.
            $bg86LowerEdge = (float) $bg86->y + $bg86HalfY;
            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '83')
                ->update([
                    'x' => round($sofaLeft - $bg83HalfX, 5),
                    'y' => round($bg86LowerEdge + $bg83HalfY, 5),
                ]);

            // BG-84: directly against the left side of BG-86, aligned with it.
            $bg86Left = $bg86X - $bg86HalfX;
            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '84')
                ->update([
                    'x' => round($bg86Left - $bg84HalfX, 5),
                    'y' => round((float) $bg86->y, 5),
                ]);

            // BG-102: against the lower outside wall on the furniture map.
            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '102')
                ->update([
                    'y' => round(((float) $floor->width / 2) - $bg102HalfY, 5),
                ]);

            // BG-45: against the upper outside wall on the furniture map.
            DB::table('furniture_items')
                ->where('floor_id', 'ground')
                ->where('item_id', '45')
                ->update([
                    'y' => round(-((float) $floor->width / 2) + $bg45HalfY, 5),
                ]);

            // Move the kitchen run 40 cm left, toward BG-73.
            foreach (['53', '901', '52', '50', '46', '47', '45'] as $id) {
                $get($id);
                DB::table('furniture_items')
                    ->where('floor_id', 'ground')
                    ->where('item_id', $id)
                    ->update(['x' => DB::raw('x - 0.4')]);
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
            foreach (['53', '901', '52', '50', '46', '47', '45'] as $id) {
                DB::table('furniture_items')
                    ->where('floor_id', 'ground')
                    ->where('item_id', $id)
                    ->update(['x' => DB::raw('x + 0.4')]);
            }

            DB::table('furniture_items')->where('floor_id', 'ground')->where('item_id', '83')->update(['x' => -2.59430, 'y' => -1.37500]);
            DB::table('furniture_items')->where('floor_id', 'ground')->where('item_id', '84')->update(['x' => -4.87740, 'y' => -2.43270]);
            DB::table('furniture_items')->where('floor_id', 'ground')->where('item_id', '86')->update(['x' => -3.21700]);
            DB::table('furniture_items')->where('floor_id', 'ground')->where('item_id', '102')->update(['y' => 2.22120]);
            DB::table('furniture_items')->where('floor_id', 'ground')->where('item_id', '45')->update(['y' => -2.22120]);

            DB::table('furniture_layouts')->where('id', 1)->update([
                'revision' => DB::raw('revision + 1'),
                'updated_at' => now(),
            ]);
        });
    }
};
