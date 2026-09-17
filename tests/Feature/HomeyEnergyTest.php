<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomeyEnergyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['homey.url' => 'http://homey.test', 'homey.token' => 'private-test-key', 'cache.default' => 'array']);
        Cache::flush();
        Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-09-14 22:30:00', 'UTC'));
    }

    public function test_reports_use_dutch_dates_preserve_zero_and_missing_values_and_cache(): void
    {
        Http::fake([
            'homey.test/api/manager/energy/report/day*' => Http::response([
                'electricity' => ['consumedPeriod' => 18.834, 'importedPeriod' => 14.577, 'exportedPeriod' => 0, 'generatedPeriod' => null, 'devices' => ['private' => 'data']],
                'gas' => ['importedPeriod' => 0.797], 'water' => ['importedPeriod' => 0.39],
            ]),
            'homey.test/api/manager/energy/report/week*' => Http::response([
                'electricity' => [], 'subReports' => [
                    '2026-09-14' => ['electricity' => ['consumedPeriod' => 30.078]],
                    '2026-09-15' => ['electricity' => ['consumedPeriod' => 0]],
                    '2026-09-16' => ['electricity' => ['consumedPeriod' => null]],
                ],
            ]),
        ]);
        $this->getJson('/dashboard/energy')->assertOk()
            ->assertJsonPath('date', '2026-09-15')->assertJsonPath('week', '2026-W38')
            ->assertJsonPath('today.consumed', 18.834)->assertJsonPath('today.exported', 0)
            ->assertJsonPath('today.generated', null)->assertJsonPath('today.water', 0.39)
            ->assertJsonPath('days.2026-09-16.consumed', null)
            ->assertDontSee('private')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/dashboard/energy')->assertOk();
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'date=2026-09-15'));
    }

    public function test_missing_week_does_not_hide_today(): void
    {
        Http::fake([
            'homey.test/api/manager/energy/report/day*' => Http::response(['electricity' => ['consumedPeriod' => 0]]),
            'homey.test/api/manager/energy/report/week*' => Http::response([], 503),
        ]);
        $this->getJson('/dashboard/energy')->assertOk()->assertJsonPath('today.consumed', 0)->assertJsonPath('days', []);
    }

    public function test_permission_errors_are_not_sample_data_or_zero(): void
    {
        Http::fake(['*' => Http::response(['error' => 'private upstream details'], 403)]);
        $this->getJson('/dashboard/energy')->assertStatus(503)->assertJsonPath('available', false)
            ->assertJsonPath('today', null)->assertDontSee('private upstream details');
    }

    public function test_unconfigured_homey_makes_no_requests(): void
    {
        config(['homey.token' => null]);
        $this->getJson('/dashboard/energy')->assertStatus(503);
        Http::assertNothingSent();
    }
}
