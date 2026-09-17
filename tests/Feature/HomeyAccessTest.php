<?php

namespace Tests\Feature;

use App\Services\HomeyClient;
use Tests\TestCase;

class HomeyAccessTest extends TestCase
{
    public function test_door_contact_and_lock_are_independent_and_mapped_by_id(): void
    {
        config(['homey.access' => [['id' => 'door', 'name' => 'Achterdeur', 'zone' => 'Begane grond']]]);
        $result = app(HomeyClient::class)->access([
            ['id' => 'camera', 'name' => 'Achterdeur', 'available' => true],
            ['id' => 'door', 'name' => 'Nieuwe naam', 'zone_name' => 'Tuin', 'available' => true, 'measurements' => [
                'alarm_contact' => ['value' => false, 'updated_at' => 1761850302509],
                'locked' => ['value' => false, 'updated_at' => 1768197602134],
            ]],
        ]);
        $this->assertSame('Nieuwe naam', $result[0]['name']);
        $this->assertSame('Tuin', $result[0]['zone']);
        $this->assertFalse($result[0]['open']);
        $this->assertFalse($result[0]['locked']);
        $this->assertSame(1761850302509, $result[0]['contact_updated_at']);
    }

    public function test_missing_or_unavailable_devices_never_appear_closed_or_locked(): void
    {
        config(['homey.access' => [['id' => 'door', 'name' => 'Achterdeur', 'zone' => 'Begane grond']]]);
        foreach ([[], [['id' => 'door', 'available' => false, 'measurements' => ['alarm_contact' => ['value' => false], 'locked' => ['value' => true]]]], [['id' => 'door', 'available' => true, 'measurements' => []]]] as $devices) {
            $result = app(HomeyClient::class)->access($devices);
            $this->assertNull($result[0]['open']);
            $this->assertNull($result[0]['locked']);
        }
    }
}
