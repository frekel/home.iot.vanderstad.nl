<?php

namespace Tests\Feature;

use App\Services\HouseLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HouseGeometryTest extends TestCase
{
    use RefreshDatabase;

    public function test_house_endpoint_is_entirely_database_backed(): void
    {
        $this->getJson('/dashboard/house')
            ->assertOk()
            ->assertJsonCount(3, 'floors')
            ->assertJsonPath('floors.0.id', 'ground')
            ->assertJsonPath('floors.0.display_mirrored', false)
            ->assertJsonPath('floors.1.id', 'upper')
            ->assertJsonPath('floors.1.display_mirrored', true)
            ->assertJsonPath('floors.2.id', 'attic')
            ->assertJsonPath('floors.2.display_mirrored', true);

        DB::table('house_floors')->where('id', 'upper')->update(['label' => 'Database floor']);
        DB::table('house_rooms')
            ->where('floor_id', 'upper')
            ->where('room_id', 'bathroom')
            ->update(['label' => 'Database bathroom']);

        $data = $this->getJson('/dashboard/house')->assertOk()->json();
        $upper = collect($data['floors'])->firstWhere('id', 'upper');
        $bathroom = collect($upper['rooms'])->firstWhere('id', 'bathroom');

        $this->assertSame('Database floor', $upper['label']);
        $this->assertSame('Database bathroom', $bathroom['label']);
    }

    public function test_measured_geometry_is_seeded_as_the_authoritative_shape(): void
    {
        $layout = app(HouseLayout::class)->data();
        $upper = collect($layout['floors'])->firstWhere('id', 'upper');
        $attic = collect($layout['floors'])->firstWhere('id', 'attic');

        $middleWall = collect($upper['walls'])->firstWhere('name', 'between-bedroom-doors-63cm');
        $this->assertEquals(-0.3499, $middleWall['x1']);
        $this->assertEquals(0.2801, $middleWall['x2']);
        $this->assertEquals(0.2292, $middleWall['y1']);
        $this->assertEquals(0.2292, $middleWall['y2']);

        $atticBedroom = collect($attic['rooms'])->firstWhere('id', 'attic-bedroom');
        $this->assertEquals([
            [-3.4, -2.75],
            [1.0, -2.75],
            [1.0, 0.55],
            [-3.4, 0.55],
        ], $atticBedroom['polygon']);

        $this->assertTrue(Schema::hasTable('house_floors'));
        $this->assertTrue(Schema::hasTable('house_rooms'));
        $this->assertTrue(Schema::hasTable('house_walls'));
    }

    public function test_furniture_is_normalized_and_legacy_overrides_are_gone(): void
    {
        $this->assertTrue(Schema::hasTable('furniture_items'));
        $this->assertFalse(Schema::hasColumn('furniture_layouts', 'overrides'));

        $before = DB::table('furniture_items')
            ->where('floor_id', 'ground')
            ->where('item_id', '82')
            ->first();

        $this->putJson('/dashboard/furniture', [
            'revision' => 0,
            'items' => [[
                'floor' => 'ground',
                'id' => '82',
                'width' => 130,
                'depth' => 40,
                'height' => 90,
                'base_z' => 0,
            ]],
        ])->assertOk()->assertJsonPath('revision', 1);

        $after = DB::table('furniture_items')
            ->where('floor_id', 'ground')
            ->where('item_id', '82')
            ->first();

        $this->assertNotEquals((float) $before->width, (float) $after->width);
        $this->assertEquals(1.30, (float) $after->width);
        $this->assertEquals(0.40, (float) $after->depth);
        $this->assertEquals(0.90, (float) $after->height);
    }
}
