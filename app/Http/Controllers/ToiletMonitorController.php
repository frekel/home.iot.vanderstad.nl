<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ToiletMonitorController extends Controller
{
    private const TIMESTAMP = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?(?:Z|[+-]\d{2}:\d{2})$/';

    public function event(Request $request): JsonResponse
    {
        $data = $this->validated($request, [
            'device_id' => ['required', 'string', Rule::in([config('toilet.device_id')])],
            'event_id' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
            'event' => ['required', Rule::in(['flush'])],
            'timestamp' => ['required', 'string', 'regex:'.self::TIMESTAMP, 'date', 'before_or_equal:'.now()->addMinutes(5)->toIso8601String()],
            'flushes_total' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ]);
        $occurredAt = CarbonImmutable::parse($data['timestamp'])->utc()->format('Y-m-d H:i:s');

        $inserted = DB::transaction(function () use ($data, $occurredAt): bool {
            DB::table('toilet_devices')->insertOrIgnore(['device_id' => $data['device_id'], 'flushes_total' => 0]);
            $existing = DB::table('toilet_events')->where('device_id', $data['device_id'])
                ->where('event_id', $data['event_id'])->first();

            if ($existing) {
                abort_unless($existing->event === 'flush'
                    && $existing->occurred_at === $occurredAt
                    && (int) $existing->flushes_total === (int) $data['flushes_total'], 409, 'Conflicting event ID.');

                return false;
            }

            DB::table('toilet_events')->insert([
                'device_id' => $data['device_id'],
                'event_id' => $data['event_id'],
                'event' => $data['event'],
                'flushes_total' => $data['flushes_total'],
                'occurred_at' => $occurredAt,
                'received_at' => now()->utc()->format('Y-m-d H:i:s'),
            ]);

            // Legacy event logging must never change the ESP32-owned total.
            return true;
        }, 3);

        return response()->json([
            'inserted' => $inserted ? 1 : 0,
            'duplicate' => ! $inserted,
        ], $inserted ? 201 : 200);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $data = $this->validated($request, [
            'device_id' => ['required', 'string', Rule::in([config('toilet.device_id')])],
            'tank' => ['required', Rule::in(['full', 'low'])],
            'float_up' => ['required', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_bool($value)) {
                    $fail('Float position must be a JSON boolean.');
                }
            }],
            'flushes_total' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'observed_at' => ['required', 'string', 'regex:'.self::TIMESTAMP, 'date', 'before_or_equal:'.now()->addMinutes(5)->toIso8601String()],
            'low_since' => ['present', 'nullable', 'string', 'regex:'.self::TIMESTAMP, 'date'],
        ]);

        $observedAt = CarbonImmutable::parse($data['observed_at'])->utc();
        $lowSince = $data['low_since'] !== null ? CarbonImmutable::parse($data['low_since'])->utc() : null;
        if ($data['float_up'] !== ($data['tank'] === 'full')
            || ($data['tank'] === 'low' && ($lowSince === null || $lowSince->gt($observedAt)))
            || ($data['tank'] === 'full' && $lowSince !== null)) {
            throw ValidationException::withMessages(['tank' => 'Float position, tank state and low_since must agree.']);
        }

        $accepted = DB::transaction(function () use ($data, $observedAt, $lowSince): bool {
            $id = $data['device_id'];
            DB::table('toilet_devices')->insertOrIgnore(['device_id' => $id, 'flushes_total' => 0]);
            $device = DB::table('toilet_devices')->where('device_id', $id)->lockForUpdate()->first();
            $observed = $observedAt->format('Y-m-d H:i:s');

            $updates = [
                'last_seen_at' => now()->utc()->format('Y-m-d H:i:s'),
            ];
            $accepted = $device->observed_at === null || $observed >= $device->observed_at;

            if ($accepted) {
                // The latest ESP32 heartbeat is the source of truth,
                // including a deliberate decrease to zero after reflashing.
                $incomingTotal = (int) $data['flushes_total'];
                $previousTotal = (int) $device->flushes_total;
                $updates += [
                    'observed_at' => $observed,
                    'tank' => $data['tank'],
                    'float_up' => $data['float_up'],
                    'low_since' => $lowSince?->format('Y-m-d H:i:s'),
                    'flushes_total' => $incomingTotal,
                    'last_flush_at' => $incomingTotal > $previousTotal
                        ? $observed
                        : ($incomingTotal < $previousTotal ? null : $device->last_flush_at),
                ];
            }

            DB::table('toilet_devices')->where('device_id', $id)->update($updates);

            return $accepted;
        }, 3);

        return response()->json(['accepted' => $accepted]);
    }

    public function state(): JsonResponse
    {
        $now = CarbonImmutable::now('UTC');
        $device = DB::table('toilet_devices')->where('device_id', config('toilet.device_id'))->first();

        $lastSeen = $device?->last_seen_at ? CarbonImmutable::parse($device->last_seen_at, 'UTC') : null;
        $observed = $device?->observed_at ? CarbonImmutable::parse($device->observed_at, 'UTC') : null;
        $cutoff = $now->subSeconds(config('toilet.stale_after_seconds'));
        $stale = $lastSeen === null || $observed === null || $lastSeen->lt($cutoff) || $observed->lt($cutoff);

        // One counter only: the last ESP32 heartbeat's authoritative total.
        $totalCount = $device !== null ? (int) $device->flushes_total : null;
        $lastFlush = $device?->last_flush_at
            ? CarbonImmutable::parse($device->last_flush_at, 'UTC')->timestamp
            : null;

        $floatUp = $device?->float_up === null ? null : (bool) $device->float_up;

        return response()->json([
            'device_id' => config('toilet.device_id'),
            'tank' => $device?->tank ?? 'unknown',
            'full_when' => 'open',
            'float_up' => $floatUp,
            'switch_closed' => $floatUp === null ? null : ! $floatUp,
            'low_since' => $device?->low_since ? CarbonImmutable::parse($device->low_since, 'UTC')->timestamp : null,
            'flushes_total' => $totalCount,
            'last_flush' => $lastFlush,
            'updated' => $lastSeen?->timestamp,
            'now' => $now->timestamp,
            'stale' => $stale,
            'warn' => config('toilet.warn_low_seconds'),
            'crit' => config('toilet.crit_low_seconds'),
        ])->header('Cache-Control', 'no-store, private');
    }

    private function validated(Request $request, array $rules): array
    {
        abort_unless($request->isJson(), 415, 'Send application/json.');
        if (array_diff(array_keys($request->all()), array_keys($rules))) {
            throw ValidationException::withMessages(['payload' => 'Unexpected fields in toilet monitor payload.']);
        }

        return $request->validate($rules);
    }
}
