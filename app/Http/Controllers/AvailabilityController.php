<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AvailabilityController extends Controller
{
    private const TIMESTAMP = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?(?:Z|[+-]\d{2}:\d{2})$/';

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->isJson(), 415, 'Send application/json.');
        $data = $request->validate([
            'devices' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'devices.*' => ['required', 'array:device_id,name,zone,driver,available,observed_at'],
            'devices.*.device_id' => ['required', 'string', 'max:128', 'distinct:strict', 'regex:/^[A-Za-z0-9_-]+$/'],
            'devices.*.name' => ['required', 'string', 'max:255'],
            'devices.*.zone' => ['present', 'nullable', 'string', 'max:255'],
            'devices.*.driver' => ['required', 'string', 'max:255'],
            'devices.*.available' => ['required', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_bool($value)) {
                    $fail('Availability must be a JSON boolean.');
                }
            }],
            'devices.*.observed_at' => ['required', 'string', 'regex:'.self::TIMESTAMP, 'date', 'before_or_equal:'.now()->addMinutes(5)->toIso8601String()],
        ]);
        $devices = collect($data['devices'])->sortBy('device_id');
        $result = DB::transaction(function () use ($devices): array {
            $inserted = 0;
            $duplicates = 0;
            foreach ($devices as $device) {
                $device['observed_at'] = CarbonImmutable::parse($device['observed_at'])->utc()->format('Y-m-d H:i:s');
                DB::table('availability_devices')->insertOrIgnore([
                    'device_id' => $device['device_id'], 'name' => $device['name'],
                    'zone' => $device['zone'], 'driver' => $device['driver'],
                ]);
                $latest = DB::table('availability_devices')->where('device_id', $device['device_id'])->lockForUpdate()->first();
                $existing = DB::table('availability_snapshots')->where('device_id', $device['device_id'])->where('observed_at', $device['observed_at'])->first();
                if ($existing) {
                    abort_unless($existing->name === $device['name'] && $existing->zone === $device['zone'] && $existing->driver === $device['driver'] && (bool) $existing->available === $device['available'], 409, 'A different snapshot already exists at this timestamp.');
                    $duplicates++;

                    continue;
                }
                if ($latest->observed_at !== null && $device['observed_at'] <= $latest->observed_at) {
                    throw ValidationException::withMessages(['devices' => 'An observation is older than the latest stored status.']);
                }
                $device['received_at'] = now()->utc()->format('Y-m-d H:i:s');
                $id = DB::table('availability_snapshots')->insertGetId($device);
                if ($latest->available !== null && (bool) $latest->available !== $device['available']) {
                    DB::table('availability_transitions')->insert([
                        'snapshot_id' => $id, 'device_id' => $device['device_id'],
                        'from_available' => (bool) $latest->available, 'to_available' => $device['available'],
                        'previous_observed_at' => $latest->observed_at, 'observed_at' => $device['observed_at'],
                    ]);
                }
                DB::table('availability_devices')->where('device_id', $device['device_id'])->update($device);
                $inserted++;
            }

            return ['inserted' => $inserted, 'duplicates' => $duplicates];
        }, 3);

        return response()->json($result, $result['inserted'] ? 201 : 200);
    }

    public function latest(Request $request): JsonResponse
    {
        $query = $this->query($request, 'availability_devices', false);
        $page = $query->orderBy('device_id')->paginate($request->integer('per_page', 50))->withQueryString();
        $page->getCollection()->transform(function (object $row): object {
            $row->stale = $row->observed_at === null || CarbonImmutable::parse($row->observed_at, 'UTC')->lt(now()->utc()->subMinutes(config('availability.stale_after_minutes')));

            return $this->serialize($row);
        });

        return response()->json($page);
    }

    public function history(Request $request): JsonResponse
    {
        return $this->records($request, 'availability_snapshots');
    }

    public function transitions(Request $request): JsonResponse
    {
        return $this->records($request, 'availability_transitions');
    }

    public function report(Request $request): JsonResponse
    {
        $rows = $this->query($request, 'availability_snapshots')->select('device_id')
            ->selectRaw('COUNT(*) as samples, SUM(CASE WHEN available = 1 THEN 1 ELSE 0 END) as online_samples, SUM(CASE WHEN available = 0 THEN 1 ELSE 0 END) as offline_samples')
            ->groupBy('device_id')->orderBy('device_id')->paginate($request->integer('per_page', 50))->withQueryString();
        $rows->getCollection()->transform(function (object $row): object {
            foreach (['samples', 'online_samples', 'offline_samples'] as $key) {
                $row->$key = (int) $row->$key;
            }
            $row->online_sample_percent = round(100 * $row->online_samples / $row->samples, 2);

            return $row;
        });

        return response()->json($rows);
    }

    private function records(Request $request, string $table): JsonResponse
    {
        $page = $this->query($request, $table)->orderByDesc('observed_at')->orderByDesc('id')
            ->paginate($request->integer('per_page', 50))->withQueryString();
        $page->getCollection()->transform(fn (object $row): object => $this->serialize($row));

        return response()->json($page);
    }

    private function query(Request $request, string $table, bool $period = true): Builder
    {
        $data = $request->validate([
            'device_id' => ['sometimes', 'required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
            'from' => ['sometimes', 'required', 'string', 'regex:'.self::TIMESTAMP, 'date'],
            'to' => ['sometimes', 'required', 'string', 'regex:'.self::TIMESTAMP, 'date'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = DB::table($table);
        if (isset($data['device_id'])) {
            $query->where('device_id', $data['device_id']);
        }
        if ($period) {
            $from = CarbonImmutable::parse($data['from'] ?? now()->subDays(30)->toIso8601String())->utc();
            $to = CarbonImmutable::parse($data['to'] ?? now()->toIso8601String())->utc();
            if ($to->lt($from) || $from->diffInDays($to) > 366) {
                throw ValidationException::withMessages(['to' => 'Select a period of at most 366 days after from.']);
            }
            $query->whereBetween('observed_at', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')]);
        }

        return $query;
    }

    private function serialize(object $row): object
    {
        foreach (['available', 'from_available', 'to_available'] as $key) {
            if (property_exists($row, $key) && $row->$key !== null) {
                $row->$key = (bool) $row->$key;
            }
        }
        foreach (['observed_at', 'previous_observed_at', 'received_at'] as $key) {
            if (isset($row->$key)) {
                $row->$key = CarbonImmutable::parse($row->$key, 'UTC')->toIso8601String();
            }
        }

        return $row;
    }
}
