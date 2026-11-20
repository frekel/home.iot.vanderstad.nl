<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ToiletMonitorTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-toilet-write-token-at-least-32-characters';

    private const API = 'https://localhost/api/toilet';

    protected function setUp(): void
    {
        parent::setUp();
        config(['toilet.write_token' => self::TOKEN]);
        $this->travelTo(CarbonImmutable::parse('2026-11-20T09:00:00Z'));
    }

    private function event(array $overrides = []): TestResponse
    {
        return $this->postJson(self::API.'/events', array_replace([
            'device_id' => 'toilet-01',
            'event_id' => 'toilet-01-00000020',
            'event' => 'flush',
            'timestamp' => '2026-11-20T09:00:00Z',
            'flushes_total' => 20,
        ], $overrides), ['Authorization' => 'Bearer '.self::TOKEN]);
    }

    private function heartbeat(array $overrides = []): TestResponse
    {
        return $this->postJson(self::API.'/heartbeat', array_replace([
            'device_id' => 'toilet-01',
            'tank' => 'full',
            'float_up' => true,
            'flushes_total' => 19,
            'observed_at' => '2026-11-20T09:00:00Z',
            'low_since' => null,
        ], $overrides), ['Authorization' => 'Bearer '.self::TOKEN]);
    }

    public function test_dashboard_returns_stale_unknown_without_a_device(): void
    {
        $this->getJson(self::API.'/state')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('device_id', 'toilet-01')
            ->assertJsonPath('tank', 'unknown')
            ->assertJsonPath('stale', true)
            ->assertJsonPath('flushes_total', null)
            ->assertJsonPath('flushes_today', null)
            ->assertJsonPath('float_up', null)
            ->assertJsonPath('warn', 120)
            ->assertJsonPath('crit', 600);
    }

    public function test_post_endpoints_require_https_and_a_dedicated_strong_token(): void
    {
        foreach (['events', 'heartbeat'] as $endpoint) {
            $this->postJson(self::API.'/'.$endpoint, [])->assertUnauthorized();
            $this->postJson(self::API.'/'.$endpoint, [], ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
            $this->postJson('http://localhost/api/toilet/'.$endpoint, [], ['Authorization' => 'Bearer '.self::TOKEN])->assertForbidden();
        }

        config(['toilet.write_token' => 'short']);
        $this->event()->assertStatus(503);
        $this->heartbeat()->assertStatus(503);

        $this->assertDatabaseCount('toilet_devices', 0);
        $this->assertDatabaseCount('toilet_events', 0);
    }

    public function test_heartbeat_updates_the_tank_without_creating_fake_flushes(): void
    {
        $this->heartbeat()->assertOk()->assertJsonPath('accepted', true);
        $this->getJson(self::API.'/state')
            ->assertOk()
            ->assertJsonPath('tank', 'full')
            ->assertJsonPath('float_up', true)
            ->assertJsonPath('switch_closed', false)
            ->assertJsonPath('stale', false)
            ->assertJsonPath('flushes_today', 0)
            ->assertJsonPath('flushes_total', 19);

        $this->travelTo(CarbonImmutable::parse('2026-11-20T09:00:10Z'));
        $this->heartbeat([
            'tank' => 'low',
            'float_up' => false,
            'low_since' => '2026-11-20T09:00:09Z',
            'observed_at' => '2026-11-20T09:00:10Z',
        ])->assertOk();

        $this->getJson(self::API.'/state')
            ->assertJsonPath('tank', 'low')
            ->assertJsonPath('float_up', false)
            ->assertJsonPath('switch_closed', true)
            ->assertJsonPath('low_since', CarbonImmutable::parse('2026-11-20T09:00:09Z')->timestamp);

        $this->assertDatabaseCount('toilet_events', 0);
    }

    public function test_event_is_idempotent_and_rejects_reuse_with_conflicting_content(): void
    {
        $this->event()->assertCreated()->assertJson(['inserted' => 1, 'duplicate' => false]);
        $this->event()->assertOk()->assertJson(['inserted' => 0, 'duplicate' => true]);
        $this->event(['flushes_total' => 21])->assertConflict();
        $this->event(['timestamp' => '2026-11-20T09:00:01Z'])->assertConflict();

        $this->assertDatabaseCount('toilet_events', 1);
        $this->assertDatabaseHas('toilet_devices', ['device_id' => 'toilet-01', 'flushes_total' => 20]);

        $this->getJson(self::API.'/state')
            ->assertJsonPath('flushes_today', 1)
            ->assertJsonPath('flushes_total', 20)
            ->assertJsonPath('last_flush', CarbonImmutable::parse('2026-11-20T09:00:00Z')->timestamp)
            ->assertJsonPath('stale', true);
    }

    public function test_delayed_events_do_not_roll_back_the_all_time_counter_or_last_flush(): void
    {
        $this->event(['event_id' => 'toilet-01-21', 'flushes_total' => 21])->assertCreated();
        $this->event(['event_id' => 'toilet-01-18', 'timestamp' => '2026-11-19T21:00:00Z', 'flushes_total' => 18])->assertCreated();

        $this->assertDatabaseCount('toilet_events', 2);
        $this->assertDatabaseHas('toilet_devices', [
            'device_id' => 'toilet-01',
            'flushes_total' => 21,
            'last_flush_at' => '2026-11-20 09:00:00',
        ]);
        $this->getJson(self::API.'/state')->assertJsonPath('flushes_today', 1);
    }

    public function test_old_heartbeat_cannot_override_new_sensor_state(): void
    {
        $this->heartbeat()->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-11-20T09:00:10Z'));
        $this->heartbeat([
            'tank' => 'low',
            'float_up' => false,
            'low_since' => '2026-11-20T09:00:09Z',
            'observed_at' => '2026-11-20T09:00:10Z',
        ])->assertJsonPath('accepted', true);

        $this->heartbeat([
            'tank' => 'full',
            'float_up' => true,
            'observed_at' => '2026-11-20T09:00:00Z',
        ])->assertJsonPath('accepted', false);

        $this->getJson(self::API.'/state')->assertJsonPath('tank', 'low');
    }

    public function test_sensor_ages_into_stale_status(): void
    {
        $this->heartbeat()->assertOk();
        $this->travel(91)->seconds();

        $this->getJson(self::API.'/state')
            ->assertJsonPath('stale', true)
            ->assertJsonPath('tank', 'full')
            ->assertJsonPath('flushes_total', 19);
    }

    public function test_daily_flush_count_uses_amsterdam_calendar_days(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20T22:59:00Z'));
        $this->event(['timestamp' => '2026-11-20T22:59:00Z'])->assertCreated();
        $this->getJson(self::API.'/state')
            ->assertJsonPath('day', '2026-11-20')
            ->assertJsonPath('flushes_today', 1);

        $this->travelTo(CarbonImmutable::parse('2026-11-20T23:01:00Z'));
        $this->getJson(self::API.'/state')
            ->assertJsonPath('day', '2026-11-21')
            ->assertJsonPath('flushes_today', 0);

        $this->event([
            'event_id' => 'toilet-01-00000021',
            'timestamp' => '2026-11-20T23:01:00Z',
            'flushes_total' => 21,
        ])->assertCreated();

        $this->getJson(self::API.'/state')
            ->assertJsonPath('day', '2026-11-21')
            ->assertJsonPath('flushes_today', 1)
            ->assertJsonPath('flushes_total', 21);
    }

    public function test_reset_clears_both_visible_counters_without_reusing_esp32_event_ids(): void
    {
        $this->heartbeat()->assertOk();
        $this->event()->assertCreated();
        $this->getJson(self::API.'/state')
            ->assertJsonPath('flushes_total', 20)
            ->assertJsonPath('flushes_today', 1);

        $this->travelTo(CarbonImmutable::parse('2026-11-20T09:01:00Z'));
        $this->postJson('https://localhost/dashboard/toilet/reset', ['confirm' => 'RESET'])
            ->assertOk()
            ->assertJsonPath('flushes_total', 0)
            ->assertJsonPath('flushes_today', 0);

        $this->getJson(self::API.'/state')
            ->assertJsonPath('flushes_total', 0)
            ->assertJsonPath('flushes_today', 0)
            ->assertJsonPath('last_flush', null);

        // Old event IDs stay in place, and a delayed queued test event
        // must not bring earlier tests back into the visible counts.
        $this->event()->assertOk()->assertJsonPath('duplicate', true);
        $this->event([
            'event_id' => 'toilet-01-00000019',
            'timestamp' => '2026-11-20T08:59:30Z',
            'flushes_total' => 19,
        ])->assertCreated();
        $this->heartbeat([
            'flushes_total' => 21,
            'observed_at' => '2026-11-20T09:01:00Z',
        ])->assertOk();
        $this->getJson(self::API.'/state')
            ->assertJsonPath('flushes_total', 0)
            ->assertJsonPath('flushes_today', 0);

        $this->travelTo(CarbonImmutable::parse('2026-11-20T09:02:00Z'));
        $this->event([
            'event_id' => 'toilet-01-00000022',
            'timestamp' => '2026-11-20T09:02:00Z',
            'flushes_total' => 22,
        ])->assertCreated();

        $this->getJson(self::API.'/state')
            ->assertJsonPath('flushes_total', 1)
            ->assertJsonPath('flushes_today', 1)
            ->assertJsonPath('last_flush', CarbonImmutable::parse('2026-11-20T09:02:00Z')->timestamp);

        $this->assertDatabaseCount('toilet_events', 3);
        $this->assertDatabaseHas('toilet_devices', [
            'device_id' => 'toilet-01',
            'flushes_total' => 22,
        ]);

        // A second reset opens a new counting period.
        $this->travelTo(CarbonImmutable::parse('2026-11-20T09:03:00Z'));
        $this->postJson('https://localhost/dashboard/toilet/reset', ['confirm' => 'RESET'])->assertOk();
        $this->getJson(self::API.'/state')
            ->assertJsonPath('flushes_total', 0)
            ->assertJsonPath('flushes_today', 0)
            ->assertJsonPath('last_flush', null);
    }

    public function test_reset_requires_https_json_and_explicit_confirmation(): void
    {
        $this->postJson('http://localhost/dashboard/toilet/reset', ['confirm' => 'RESET'])
            ->assertForbidden();

        $this->post('https://localhost/dashboard/toilet/reset', ['confirm' => 'RESET'])
            ->assertStatus(415);

        $this->postJson('https://localhost/dashboard/toilet/reset', [])
            ->assertUnprocessable();

        $this->postJson('https://localhost/dashboard/toilet/reset', ['confirm' => 'no'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('toilet_devices', 0);
    }

    public function test_reset_allows_repeated_confirmations_during_testing(): void
    {
        // Regression: the previous limit of three resets per minute caused 429s
        // during the bench test and prevented the user from starting clean.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('https://localhost/dashboard/toilet/reset', ['confirm' => 'RESET'])
                ->assertOk()
                ->assertJsonPath('flushes_total', 0)
                ->assertJsonPath('flushes_today', 0);
        }
    }

    public function test_reset_before_first_heartbeat_starts_at_zero_and_ignores_late_tests(): void
    {
        $this->postJson('https://localhost/dashboard/toilet/reset', ['confirm' => 'RESET'])
            ->assertOk();

        $this->getJson(self::API.'/state')
            ->assertJsonPath('stale', true)
            ->assertJsonPath('flushes_total', 0)
            ->assertJsonPath('flushes_today', 0);

        $this->heartbeat()->assertOk();
        $this->getJson(self::API.'/state')
            ->assertJsonPath('flushes_total', 0)
            ->assertJsonPath('flushes_today', 0);
    }

    public function test_bad_payloads_do_not_write_any_data(): void
    {
        foreach ([
            ['device_id' => 'toilet-upstairs'],
            ['event' => 'heartbeat'],
            ['event_id' => '../bad'],
            ['timestamp' => '2026-11-20 09:00:00'],
            ['timestamp' => '2026-11-20T10:00:00Z'],
            ['flushes_total' => -1],
            ['extra' => 'unexpected'],
        ] as $bad) {
            $this->event($bad)->assertUnprocessable();
        }

        foreach ([
            ['tank' => 'low', 'float_up' => true, 'low_since' => '2026-11-20T09:00:00Z'],
            ['tank' => 'low', 'float_up' => false],
            ['tank' => 'full', 'low_since' => '2026-11-20T09:00:00Z'],
            ['float_up' => 'false'],
            ['observed_at' => '2026-11-20T10:00:00Z'],
        ] as $bad) {
            $this->heartbeat($bad)->assertUnprocessable();
        }

        $this->assertDatabaseCount('toilet_devices', 0);
        $this->assertDatabaseCount('toilet_events', 0);
    }

    public function test_write_api_requires_json_content_type(): void
    {
        $this->post(self::API.'/events', [], ['Authorization' => 'Bearer '.self::TOKEN])->assertStatus(415);
        $this->post(self::API.'/heartbeat', [], ['Authorization' => 'Bearer '.self::TOKEN])->assertStatus(415);
    }
}
