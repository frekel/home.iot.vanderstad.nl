<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://localhost/api/v1/availability';

    private const WRITE = 'test-write-token-with-at-least-32-characters';

    private const READ = 'test-read-token-with-at-least-32-characters';

    protected function setUp(): void
    {
        parent::setUp();
        config(['availability.write_token' => self::WRITE, 'availability.read_token' => self::READ]);
    }

    private function device(array $overrides = []): array
    {
        return array_replace([
            'device_id' => 'ikea-1', 'name' => 'Lamp', 'zone' => 'Living',
            'driver' => 'homey:manager:matter:example', 'available' => true,
            'observed_at' => '2026-10-13T10:00:00Z',
        ], $overrides);
    }

    private function ingest(array $devices): TestResponse
    {
        return $this->postJson(self::URL.'/snapshots', ['devices' => $devices], ['Authorization' => 'Bearer '.self::WRITE]);
    }

    public function test_rejects_missing_wrong_and_wrong_scope_tokens(): void
    {
        foreach ([null, 'wrong', self::READ] as $token) {
            $this->postJson(self::URL.'/snapshots', ['devices' => [$this->device()]], $token ? ['Authorization' => 'Bearer '.$token] : [])->assertUnauthorized();
        }
        foreach (['devices', 'history', 'transitions', 'report'] as $endpoint) {
            $this->getJson(self::URL.'/'.$endpoint, ['Authorization' => 'Bearer '.self::WRITE])->assertUnauthorized();
            $this->getJson(self::URL.'/'.$endpoint)->assertUnauthorized();
        }
        $this->assertDatabaseCount('availability_snapshots', 0);
    }

    public function test_requires_https_and_fails_closed_without_distinct_strong_tokens(): void
    {
        $this->postJson('http://localhost/api/v1/availability/snapshots', ['devices' => [$this->device()]], ['Authorization' => 'Bearer '.self::WRITE])->assertForbidden();
        foreach (['', 'short', self::READ] as $token) {
            config(['availability.write_token' => $token]);
            $this->ingest([$this->device()])->assertStatus(503);
        }
        $this->assertDatabaseCount('availability_snapshots', 0);
    }

    public function test_stores_snapshots_and_only_actual_changes_create_transitions(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(14, 0));
        $this->ingest([$this->device()])->assertCreated()->assertJson(['inserted' => 1, 'duplicates' => 0]);
        $this->ingest([$this->device(['observed_at' => '2026-10-13T11:00:00Z'])])->assertCreated();
        $this->ingest([$this->device(['available' => false, 'observed_at' => '2026-10-13T12:00:00Z', 'name' => 'Renamed'])])->assertCreated();
        $this->ingest([$this->device(['observed_at' => '2026-10-13T13:00:00Z'])])->assertCreated();

        $this->assertDatabaseCount('availability_snapshots', 4);
        $this->assertDatabaseCount('availability_transitions', 2);
        $this->assertDatabaseHas('availability_transitions', ['from_available' => true, 'to_available' => false, 'previous_observed_at' => '2026-10-13 11:00:00', 'observed_at' => '2026-10-13 12:00:00']);
        $this->assertDatabaseHas('availability_devices', ['device_id' => 'ikea-1', 'available' => true, 'observed_at' => '2026-10-13 13:00:00']);
    }

    public function test_retries_are_idempotent_and_conflicting_or_older_batches_are_atomic(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(14, 0));
        $this->ingest([$this->device()])->assertCreated();
        $this->ingest([$this->device(['observed_at' => '2026-10-13T12:00:00+02:00'])])->assertOk()->assertJson(['inserted' => 0, 'duplicates' => 1]);
        $this->ingest([$this->device(['device_id' => 'aaa']), $this->device(['available' => false])])->assertConflict();
        $this->assertDatabaseCount('availability_devices', 1);
        $this->ingest([$this->device(['device_id' => 'aaa']), $this->device(['observed_at' => '2026-10-13T09:00:00Z'])])->assertUnprocessable()->assertJsonValidationErrors('devices');
        $this->assertDatabaseCount('availability_devices', 1);
        $this->assertDatabaseCount('availability_snapshots', 1);
        $this->assertDatabaseCount('availability_transitions', 0);
    }

    public function test_validates_boolean_timestamp_device_identity_and_batch_shape(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(14, 0));
        foreach ([['available' => 'false'], ['available' => 0], ['device_id' => '../bad'], ['observed_at' => '2026-10-13 10:00:00'], ['observed_at' => '2026-10-14T00:00:00Z'], ['name' => ''], ['driver' => ''], ['zone' => str_repeat('x', 256)], ['extra' => 'not-accepted']] as $override) {
            $this->ingest([$this->device($override)])->assertUnprocessable();
        }
        $this->ingest([])->assertUnprocessable();
        $this->ingest([$this->device(), $this->device()])->assertUnprocessable()->assertJsonValidationErrors('devices.0.device_id');
        $this->postJson(self::URL.'/snapshots', [], ['Authorization' => 'Bearer '.self::WRITE])->assertUnprocessable()->assertJsonValidationErrors('devices');
        $this->assertDatabaseCount('availability_snapshots', 0);
    }

    public function test_read_api_paginates_filters_reports_and_marks_stale_without_changing_availability(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(14, 0));
        $this->ingest([$this->device(), $this->device(['device_id' => 'ikea-2', 'zone' => null])])->assertCreated();
        $this->ingest([$this->device(['available' => false, 'observed_at' => '2026-10-13T11:00:00Z'])])->assertCreated();
        $headers = ['Authorization' => 'Bearer '.self::READ];

        $this->getJson(self::URL.'/devices?device_id=ikea-1', $headers)->assertOk()->assertJsonPath('data.0.available', false)->assertJsonPath('data.0.stale', true)->assertJsonPath('data.0.observed_at', '2026-10-13T11:00:00+00:00')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson(self::URL.'/history?device_id=ikea-1&per_page=1', $headers)->assertOk()->assertJsonPath('total', 2)->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Lamp');
        $this->getJson(self::URL.'/history?from=2026-10-13T11:00:00Z&to=2026-10-13T12:00:00Z', $headers)->assertOk()->assertJsonPath('total', 1);
        $this->getJson(self::URL.'/transitions?device_id=ikea-1', $headers)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.to_available', false);
        $this->getJson(self::URL.'/report?device_id=ikea-1', $headers)->assertOk()->assertJsonPath('data.0.samples', 2)->assertJsonPath('data.0.online_samples', 1)->assertJsonPath('data.0.offline_samples', 1)->assertJsonPath('data.0.online_sample_percent', 50);
        $this->getJson(self::URL.'/devices?device_id=unknown', $headers)->assertOk()->assertJsonPath('total', 0);
        $this->assertDatabaseCount('availability_snapshots', 3);
    }

    public function test_report_includes_latest_metadata_and_uses_seven_day_default(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(14, 0));
        $this->ingest([$this->device(['observed_at' => '2026-10-07T10:00:00Z'])])->assertCreated();
        $this->ingest([$this->device(['name' => 'Renamed', 'zone' => 'Bedroom', 'available' => false, 'observed_at' => '2026-10-14T10:00:00Z'])])->assertCreated();
        $headers = ['Authorization' => 'Bearer '.self::READ];

        $this->getJson(self::URL.'/report?device_id=ikea-1', $headers)
            ->assertOk()->assertJsonPath('data.0.name', 'Renamed')
            ->assertJsonPath('data.0.zone', 'Bedroom')
            ->assertJsonPath('data.0.available', false)
            ->assertJsonPath('data.0.stale', true)
            ->assertJsonPath('data.0.observed_at', '2026-10-14T10:00:00+00:00')
            ->assertJsonPath('data.0.samples', 1)
            ->assertJsonPath('data.0.offline_samples', 1)
            ->assertJsonPath('data.0.online_sample_percent', 0);
        $this->getJson(self::URL.'/report?device_id=ikea-1&from=2026-10-07T00:00:00Z', $headers)
            ->assertOk()->assertJsonPath('data.0.samples', 2);
    }

    public function test_read_filters_reject_invalid_or_unbounded_queries(): void
    {
        $headers = ['Authorization' => 'Bearer '.self::READ];
        foreach (['per_page=101', 'page=0', 'device_id=bad%27', 'from=2025-01-01T00:00:00Z&to=2026-10-13T00:00:00Z', 'from=2026-10-13T00:00:00Z&to=2026-10-12T00:00:00Z', 'to=not-a-date'] as $query) {
            $this->getJson(self::URL.'/history?'.$query, $headers)->assertUnprocessable();
        }
    }

    public function test_first_offline_sample_is_a_fresh_baseline_and_metadata_history_is_preserved(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(10, 0));
        $offline = $this->device(['available' => false, 'zone' => null]);
        $this->ingest([$offline])->assertCreated();
        $this->assertDatabaseCount('availability_transitions', 0);
        $this->getJson(self::URL.'/devices', ['Authorization' => 'Bearer '.self::READ])
            ->assertJsonPath('data.0.available', false)->assertJsonPath('data.0.stale', false);
        $this->travelTo(now()->setTime(11, 0));
        $this->ingest([$this->device(['name' => 'Renamed', 'zone' => 'Bedroom', 'observed_at' => '2026-10-13T11:00:00Z'])])->assertCreated();
        $this->ingest([$offline])->assertOk()->assertJsonPath('duplicates', 1);
        $this->getJson(self::URL.'/devices', ['Authorization' => 'Bearer '.self::READ])->assertJsonPath('data.0.name', 'Renamed');
        $this->getJson(self::URL.'/history', ['Authorization' => 'Bearer '.self::READ])
            ->assertJsonPath('data.1.name', 'Lamp')->assertJsonPath('data.1.zone', null);
        $this->assertDatabaseCount('availability_transitions', 1);
    }

    public function test_requires_json_content_type(): void
    {
        $this->post(self::URL.'/snapshots', ['devices' => [$this->device()]], ['Authorization' => 'Bearer '.self::WRITE, 'Accept' => 'application/json'])->assertStatus(415);
        $this->assertDatabaseCount('availability_snapshots', 0);
    }

    public function test_ingestion_is_rate_limited(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(14, 0));
        for ($i = 0; $i < 12; $i++) {
            $this->ingest([$this->device()])->assertSuccessful();
        }
        $this->ingest([$this->device()])->assertTooManyRequests();
        $this->assertDatabaseCount('availability_snapshots', 1);
    }
}
