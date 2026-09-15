<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HomeyClient
{
    public function configured(): bool
    {
        return filled(config('homey.url')) && filled(config('homey.token'));
    }

    private function http(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('Homey is not configured.');
        }

        return Http::baseUrl(rtrim(config('homey.url'), '/'))
            ->withToken(config('homey.token'))->acceptJson()->connectTimeout(3)
            ->timeout(config('homey.timeout'))->withoutRedirecting();
    }

    public function devices(): array
    {
        $response = $this->http()->get('/api/manager/devices/device/');
        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('Homey connection failed.');
        }

        return collect($response->json())->map(function ($device) {
            if (! is_array($device)) {
                throw new RuntimeException('Unexpected Homey response.');
            }

            return [
                'id' => $device['id'] ?? '',
                'name' => $device['name'] ?? 'Unnamed device',
                'zone' => $device['zone'] ?? '',
                'class' => $device['class'] ?? '',
                'available' => $device['available'] ?? null,
                'measurements' => collect($device['capabilitiesObj'] ?? [])
                    ->only(['measure_temperature', 'measure_humidity', 'measure_power', 'meter_power', 'onoff', 'alarm_contact'])
                    ->map(fn ($capability) => [
                        'value' => $capability['value'] ?? null,
                        'units' => $capability['units'] ?? null,
                        'settable' => ($capability['setable'] ?? false) === true,
                        'updated_at' => $capability['lastUpdated'] ?? null,
                    ])->all(),
            ];
        })->values()->all();
    }

    public function zones(): array
    {
        $key = 'homey.zones.'.hash('sha256', config('homey.url').config('homey.token'));

        return Cache::remember($key, 60, function () {
            $response = $this->http()->get('/api/manager/zones/zone/');
            if (! $response->successful() || ! is_array($response->json())) {
                throw new RuntimeException('Homey zones unavailable.');
            }

            return collect($response->json())->mapWithKeys(fn ($zone) => [
                $zone['id'] => ['name' => $zone['name'], 'parent' => $zone['parent'] ?? null],
            ])->all();
        });
    }

    public function rooms(array $devices): array
    {
        $indexed = collect($devices)->keyBy('id');

        return collect(config('homey.rooms'))->map(fn ($mapping) => [
            'light' => $indexed->get($mapping['light']),
            'climate' => $indexed->get($mapping['climate']),
            'light_configured' => filled($mapping['light']),
            'climate_configured' => filled($mapping['climate']),
        ])->all();
    }

    public function setRoomLight(string $room, bool $value): array
    {
        $id = config("homey.rooms.$room.light");
        if (! filled($id)) {
            throw new RuntimeException('This room has no linked light.');
        }
        $device = collect($this->devices())->firstWhere('id', $id);
        if (! $device || $device['class'] !== 'light' || $device['available'] !== true ||
            ($device['measurements']['onoff']['settable'] ?? false) !== true) {
            throw new RuntimeException('The linked light is unavailable or cannot be controlled.');
        }
        // Explicit desired state, never blind toggles or automatic retries.
        $response = $this->http()->put('/api/manager/devices/device/'.rawurlencode($id).'/capability/onoff', ['value' => $value]);
        if (! $response->successful()) {
            throw new RuntimeException('Homey did not accept the light command.');
        }

        return ['accepted' => true, 'requested' => $value];
    }
}
