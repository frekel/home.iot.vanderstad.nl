<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomeyConnectionTest extends TestCase
{
    public function test_unconfigured_homey_does_not_make_a_network_request(): void
    {
        config(['homey.url' => null, 'homey.token' => null]);
        Http::preventStrayRequests();
        $this->getJson('/dashboard/status')->assertOk()->assertJsonPath('connected', false)->assertJsonPath('devices', []);
        Http::assertNothingSent();
    }

    public function test_only_allowed_device_fields_are_exposed(): void
    {
        config(['homey.url' => 'http://homey.test', 'homey.token' => 'private-test-key']);
        Http::fake(['homey.test/*' => Http::response(['device-one' => [
            'id' => 'device-one', 'name' => 'Living light', 'settings' => ['password' => 'secret'],
            'capabilitiesObj' => ['onoff' => ['value' => true], 'private_setting' => ['value' => 'secret']],
        ]])]);
        $this->getJson('/dashboard/status')->assertOk()->assertJsonPath('connected', true)
            ->assertJsonPath('devices.0.measurements.onoff.value', true)
            ->assertDontSee('secret')->assertDontSee('private-test-key');
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer private-test-key') && $request->method() === 'GET');
    }

    public function test_upstream_errors_are_sanitized(): void
    {
        config(['homey.url' => 'http://homey.test', 'homey.token' => 'private-test-key']);
        Http::fake(['homey.test/*' => Http::response('private-test-key secret upstream body', 401)]);
        $this->getJson('/dashboard/status')->assertStatus(503)->assertJsonPath('connected', false)
            ->assertDontSee('private-test-key')->assertDontSee('secret upstream body');
    }
}
