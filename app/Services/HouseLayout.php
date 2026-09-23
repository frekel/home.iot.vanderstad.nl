<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class HouseLayout
{
    public function data(): array
    {
        $rooms = DB::table('house_rooms')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('floor_id');

        $walls = DB::table('house_walls')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('floor_id');

        $floors = DB::table('house_floors')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($floor) use ($rooms, $walls) {
                return [
                    'id' => $floor->id,
                    'label' => $floor->label,
                    'length' => (float) $floor->length,
                    'width' => (float) $floor->width,
                    'display_mirrored' => (bool) $floor->display_mirrored,
                    'rooms' => collect($rooms->get($floor->id, []))->map(fn ($room) => [
                        'id' => $room->room_id,
                        'label' => $room->label,
                        'polygon' => json_decode($room->polygon, true, flags: JSON_THROW_ON_ERROR),
                        'floor_material' => $room->floor_material,
                        'render_floor' => (bool) $room->render_floor,
                        'stair_item_id' => $room->stair_item_id,
                    ])->values()->all(),
                    'walls' => collect($walls->get($floor->id, []))->map(fn ($wall) => [
                        'id' => (int) $wall->id,
                        'name' => $wall->name,
                        'x1' => (float) $wall->x1,
                        'y1' => (float) $wall->y1,
                        'x2' => (float) $wall->x2,
                        'y2' => (float) $wall->y2,
                        'thickness' => (float) $wall->thickness,
                        'outside_dx' => $wall->outside_dx === null ? null : (int) $wall->outside_dx,
                        'outside_dy' => $wall->outside_dy === null ? null : (int) $wall->outside_dy,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        return ['floors' => $floors];
    }
}
