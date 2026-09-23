<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('house_floors', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('label');
            $table->decimal('length', 8, 4);
            $table->decimal('width', 8, 4);
            $table->boolean('display_mirrored')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('house_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('floor_id', 32);
            $table->string('room_id', 64);
            $table->string('label');
            $table->json('polygon');
            $table->string('floor_material', 16)->default('wood');
            $table->boolean('render_floor')->default(true);
            $table->string('stair_item_id', 64)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['floor_id', 'room_id']);
            $table->foreign('floor_id')->references('id')->on('house_floors')->cascadeOnDelete();
        });

        Schema::create('house_walls', function (Blueprint $table) {
            $table->id();
            $table->string('floor_id', 32);
            $table->string('name')->nullable();
            $table->decimal('x1', 9, 5);
            $table->decimal('y1', 9, 5);
            $table->decimal('x2', 9, 5);
            $table->decimal('y2', 9, 5);
            $table->decimal('thickness', 6, 4)->default(0.10);
            $table->smallInteger('outside_dx')->nullable();
            $table->smallInteger('outside_dy')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreign('floor_id')->references('id')->on('house_floors')->cascadeOnDelete();
        });

        DB::table('house_floors')->insert([
            ['id' => 'ground', 'label' => 'Ground floor', 'length' => 11.0, 'width' => 5.5, 'display_mirrored' => false, 'sort_order' => 10],
            ['id' => 'upper', 'label' => 'Upper floor', 'length' => 8.0, 'width' => 5.5, 'display_mirrored' => true, 'sort_order' => 20],
            ['id' => 'attic', 'label' => 'Attic', 'length' => 8.0, 'width' => 5.5, 'display_mirrored' => true, 'sort_order' => 30],
        ]);

        $rooms = [
            ['ground', 'entrance', 'Entrance', [[-5.5,-2.75],[-2.5659,-2.75],[-2.5659,-0.6479],[-5.5,-0.6479]], 'wood', true, null],
            ['ground', 'dining', 'Dining', [[-5.5,-0.6479],[-2.5659,-0.6479],[-2.5659,2.3641],[-5.5,2.3641]], 'wood', true, null],
            ['ground', 'living', 'Living room', [[-2.5659,-2.75],[1.4248,-2.75],[1.4248,1.7825],[0.1087,1.7825],[0.1087,2.75],[-2.5659,2.75]], 'wood', true, null],
            ['ground', 'kitchen', 'Kitchen', [[1.4248,-2.75],[5.5,-2.75],[5.5,0.049],[1.4248,0.049]], 'wood', true, null],
            ['ground', 'hall', 'Hallway', [[1.5274,0.1411],[5.5,0.1411],[5.5,2.0071],[4.2744,2.0071],[4.2744,1.4542],[1.5274,1.4542]], 'stone', true, null],
            ['ground', 'wc', 'Toilet', [[2.4874,1.5521],[4.2261,1.5521],[4.2261,2.75],[2.4874,2.75]], 'stone', true, null],
            ['ground', 'utility', 'Utility', [[4.3227,2.105],[5.5,2.105],[5.5,2.75],[4.3227,2.75]], 'stone', true, null],
            ['ground', 'closet', 'Closet', [[0.169,1.8746],[1.5274,1.8746],[1.5274,2.75],[0.169,2.75]], 'wood', true, null],
            ['ground', 'stairs', 'Stairs', [[1.5274,1.4542],[2.3847,1.4542],[2.3847,2.75],[1.5274,2.75]], 'stone', true, null],

            ['upper', 'bedroom1', 'Bedroom 1', [[-4.0,-2.75],[-0.1337,-2.75],[-0.1337,0.1855],[-4.0,0.1855]], 'wood', true, null],
            ['upper', 'bedroom2', 'Bedroom 2', [[-0.0291,-2.75],[4.0,-2.75],[4.0,0.1855],[-0.0291,0.1855]], 'wood', true, null],
            ['upper', 'office', 'Office', [[-4.0,0.2783],[-1.343,0.2783],[-1.343,2.75],[-4.0,2.75]], 'wood', true, null],
            ['upper', 'landing', 'Landing', [[-1.2442,0.2783],[1.8081,0.2783],[1.8081,1.6042],[-1.2442,1.6042]], 'wood', true, null],
            ['upper', 'upper-stairs', 'Stairs', [[-1.2442,1.6042],[1.8081,1.6042],[1.8081,2.75],[-1.2442,2.75]], 'stone', true, '905'],
            ['upper', 'bathroom', 'Bathroom', [[1.9128,0.2783],[4.0,0.2783],[4.0,2.75],[1.9128,2.75]], 'stone', true, null],

            ['attic', 'attic-bedroom', 'Bedroom', [[-3.4,-2.75],[1.0,-2.75],[1.0,0.55],[-3.4,0.55]], 'wood', true, null],
            ['attic', 'attic-closet', 'Storage', [[-3.4,0.75],[-1.5,0.75],[-1.5,2.75],[-3.4,2.75]], 'wood', true, null],
            ['attic', 'attic-hall', 'Landing', [[-1.3,0.75],[1.0,0.75],[1.0,2.75],[-1.3,2.75]], 'stone', true, '904'],
            ['attic', 'attic-stairs', 'Stairs', [[-1.3,1.70],[1.0,1.70],[1.0,2.75],[-1.3,2.75]], 'stone', false, null],
            ['attic', 'laundry', 'Laundry', [[1.2,-2.75],[3.4,-2.75],[3.4,2.75],[1.2,2.75]], 'stone', true, null],
        ];

        foreach ($rooms as $index => [$floor, $id, $label, $polygon, $material, $renderFloor, $stairItem]) {
            DB::table('house_rooms')->insert([
                'floor_id' => $floor,
                'room_id' => $id,
                'label' => $label,
                'polygon' => json_encode($polygon, JSON_THROW_ON_ERROR),
                'floor_material' => $material,
                'render_floor' => $renderFloor,
                'stair_item_id' => $stairItem,
                'sort_order' => $index + 1,
            ]);
        }

        $walls = [
            // Ground floor. The first ten segments are exterior wall segments;
            // outside_dx/outside_dy preserve the exact layer direction used by Blender.
            ['ground', 'outer-01', -5.5,-2.75,5.5,-2.75,.10,0,-1],
            ['ground', 'outer-02', -5.5,-2.75,-5.5,-2.0992,.10,-1,0],
            ['ground', 'outer-03', -5.5,-0.3772,-5.5,0.6249,.10,-1,0],
            ['ground', 'outer-04', -5.5,1.9034,-5.5,2.3641,.10,-1,0],
            ['ground', 'outer-05', -5.5,2.3641,-2.5659,2.3641,.10,0,1],
            ['ground', 'outer-06', -2.5659,2.3641,-2.5659,2.75,.10,-1,0],
            ['ground', 'outer-07', -2.5659,2.75,5.5,2.75,.10,0,1],
            ['ground', 'outer-08', 5.5,-2.75,5.5,-2.0992,.10,1,0],
            ['ground', 'outer-09', 5.5,-0.7976,5.5,0.4694,.10,1,0],
            ['ground', 'outer-10', 5.5,1.4427,5.5,2.75,.10,1,0],
            ['ground', 'inner-11', 1.4731,0.095,5.5,0.095,.10,null,null],
            ['ground', 'inner-12', 1.4731,0.095,1.4731,0.3369,.10,null,null],
            ['ground', 'inner-13', 1.4731,1.3102,1.4731,1.8285,.10,null,null],
            ['ground', 'inner-14', 0.1087,1.8285,0.1751,1.8285,.10,null,null],
            ['ground', 'inner-15', 1.1894,1.8285,1.4731,1.8285,.10,null,null],
            ['ground', 'inner-16', 0.1087,1.8285,0.1087,2.75,.10,null,null],
            ['ground', 'inner-17', 2.433,1.5003,3.1213,1.5003,.10,null,null],
            ['ground', 'inner-18', 4.045,1.5003,4.2744,1.5003,.10,null,null],
            ['ground', 'inner-19', 2.433,1.5003,2.433,2.75,.10,null,null],
            ['ground', 'inner-20', 4.2744,1.5003,4.2744,2.75,.10,null,null],
            ['ground', 'inner-21', 4.2744,2.0589,4.4133,2.0589,.10,null,null],
            ['ground', 'inner-22', 5.3913,2.0589,5.5,2.0589,.10,null,null],

            ['upper', 'outer-bottom', -4,-2.75,4,-2.75,.10,0,-1],
            ['upper', 'outer-x-negative', -4,-2.75,-4,2.75,.10,-1,0],
            ['upper', 'outer-top', -4,2.75,4,2.75,.10,0,1],
            ['upper', 'outer-x-positive', 4,-2.75,4,2.75,.10,1,0],
            ['upper', 'bedroom-divider', -.0814,-2.75,-.0814,.2292,.10,null,null],
            ['upper', 'office-bedroom-wall', -4,.2292,-1.157,.2292,.10,null,null],
            ['upper', 'between-bedroom-doors-63cm', -.3499,.2292,.2801,.2292,.10,null,null],
            ['upper', 'bedroom-bathroom-partition', 1.0988,.2292,4,.2292,.10,null,null],
            ['upper', 'office-landing-wall-1', -1.2965,.2292,-1.2965,.4092,.10,null,null],
            ['upper', 'office-landing-wall-2', -1.2965,1.2713,-1.2965,2.75,.10,null,null],
            ['upper', 'landing-bathroom-wall-1', 1.8605,.2292,1.8605,.4092,.10,null,null],
            ['upper', 'landing-bathroom-wall-2', 1.8605,1.2713,1.8605,2.75,.10,null,null],

            ['attic', 'outer-bottom', -4,-2.75,4,-2.75,.10,0,-1],
            ['attic', 'outer-x-negative', -4,-2.75,-4,2.75,.10,-1,0],
            ['attic', 'outer-top', -4,2.75,4,2.75,.10,0,1],
            ['attic', 'outer-x-positive', 4,-2.75,4,2.75,.10,1,0],
            ['attic', 'knee-wall-x-negative', -3.45,-2.75,-3.45,2.75,.10,null,null],
            ['attic', 'knee-wall-x-positive', 3.45,-2.75,3.45,2.75,.10,null,null],
            ['attic', 'room-block-washok-wall-1', 1.1,-2.75,1.1,.90,.20,null,null],
            ['attic', 'room-block-washok-wall-2', 1.1,1.80,1.1,2.75,.20,null,null],
            ['attic', 'lily-upper-wall-1', -3.4,.65,-2.85,.65,.20,null,null],
            ['attic', 'lily-upper-wall-2', -2.05,.65,-1.5,.65,.20,null,null],
            ['attic', 'lily-zolder-door-wall-1', -1.3,.65,-1.2,.65,.20,null,null],
            ['attic', 'lily-zolder-door-wall-2', -.4,.65,1.0,.65,.20,null,null],
            ['attic', 'closet-zolder-partition', -1.4,.75,-1.4,2.75,.20,null,null],
        ];

        foreach ($walls as $index => [$floor, $name, $x1, $y1, $x2, $y2, $thickness, $outsideDx, $outsideDy]) {
            DB::table('house_walls')->insert([
                'floor_id' => $floor,
                'name' => $name,
                'x1' => $x1,
                'y1' => $y1,
                'x2' => $x2,
                'y2' => $y2,
                'thickness' => $thickness,
                'outside_dx' => $outsideDx,
                'outside_dy' => $outsideDy,
                'sort_order' => $index + 1,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('house_walls');
        Schema::dropIfExists('house_rooms');
        Schema::dropIfExists('house_floors');
    }
};
