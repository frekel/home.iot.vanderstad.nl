<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomeyZoneLabelsTest extends TestCase
{
    public function test_room_labels_follow_homey_renames_without_changing_the_geometry_binding(): void
    {
        config([
            'homey.url' => 'http://zone-labels.test',
            'homey.token' => 'zone-test-key',
            'homey_layout.rooms.living' => 'room-id',
            'homey_layout.floors.ground' => 'floor-id',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'zone-labels.test/api/manager/devices/device/' => Http::response([]),
            'zone-labels.test/api/manager/zones/zone/' => Http::sequence()
                ->push(['floor-id' => ['id' => 'floor-id', 'name' => 'Begane grond', 'parent' => null], 'room-id' => ['id' => 'room-id', 'name' => 'Woonkamer', 'parent' => 'floor-id']])
                ->push(['floor-id' => ['id' => 'floor-id', 'name' => 'Begane grond', 'parent' => null], 'room-id' => ['id' => 'room-id', 'name' => 'Zitkamer', 'parent' => 'floor-id']]),
        ]);
        $this->getJson('/dashboard/status')->assertOk()
            ->assertJsonPath('layout.rooms.living', 'room-id')
            ->assertJsonPath('zones.room-id.name', 'Woonkamer')
            ->assertJsonPath('zones.room-id.parent', 'floor-id');
        Cache::forget('homey.zones.'.hash('sha256', 'http://zone-labels.testzone-test-key'));
        $this->getJson('/dashboard/status')->assertOk()
            ->assertJsonPath('layout.rooms.living', 'room-id')
            ->assertJsonPath('zones.room-id.name', 'Zitkamer');
    }
}
