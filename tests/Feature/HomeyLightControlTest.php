<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomeyLightControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['homey.url' => 'http://homey.test', 'homey.token' => 'private-test-key', 'homey.rooms' => [
            'living' => ['light' => 'allowed-light', 'climate' => null],
        ]]);
        Http::preventStrayRequests();
    }

    private function fakeDevice(string $class = 'light', bool $available = true, bool $settable = true): void
    {
        Http::fake([
            'homey.test/api/manager/devices/device/' => Http::response(['allowed-light' => [
                'id' => 'allowed-light', 'name' => 'Test lamp', 'class' => $class, 'available' => $available,
                'capabilitiesObj' => ['onoff' => ['value' => true, 'setable' => $settable]],
            ]]),
            'homey.test/api/manager/devices/device/allowed-light/capability/onoff' => Http::response(null, 204),
        ]);
    }

    public function test_only_the_configured_room_can_be_controlled(): void
    {
        $this->putJson('/dashboard/rooms/bedroom/light', ['value' => false])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_string_values_are_rejected_before_contacting_homey(): void
    {
        $this->putJson('/dashboard/rooms/living/light', ['value' => 'false'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_explicit_state_is_sent_to_the_allowlisted_light(): void
    {
        $this->fakeDevice();
        $this->putJson('/dashboard/rooms/living/light', ['value' => false, 'deviceId' => 'another-device'])
            ->assertOk()->assertJson(['accepted' => true, 'requested' => false]);
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r->url() === 'http://homey.test/api/manager/devices/device/allowed-light/capability/onoff' && $r['value'] === false);
        Http::assertSentCount(2);
    }

    public function test_an_offline_device_is_never_switched(): void
    {
        $this->fakeDevice(available: false);
        $this->putJson('/dashboard/rooms/living/light', ['value' => false])->assertStatus(503);
        Http::assertNotSent(fn ($r) => $r->method() === 'PUT');
    }

    public function test_a_socket_cannot_be_controlled_as_a_light(): void
    {
        $this->fakeDevice(class: 'socket');
        $this->putJson('/dashboard/rooms/living/light', ['value' => false])->assertStatus(503);
        Http::assertNotSent(fn ($r) => $r->method() === 'PUT');
    }

    public function test_a_readonly_capability_is_never_written(): void
    {
        $this->fakeDevice(settable: false);
        $this->putJson('/dashboard/rooms/living/light', ['value' => false])->assertStatus(503);
        Http::assertNotSent(fn ($r) => $r->method() === 'PUT');
    }
}
