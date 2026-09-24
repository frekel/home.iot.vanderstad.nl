<?php

namespace App\Services;

use App\Jobs\BuildFurniture;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FurnitureLayout
{
    /**
     * Full furniture state from the database.
     *
     * Kept as baseline() for callers/tests that used the previous service API,
     * but there is no file-backed baseline anymore.
     */
    public function baseline(): array
    {
        return $this->items();
    }

    public function items(array $legacyStored = [], bool $render = false): array
    {
        return DB::table('furniture_items')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($row) {
                $metadata = $row->metadata
                    ? json_decode($row->metadata, true, flags: JSON_THROW_ON_ERROR)
                    : [];

                return array_replace($metadata, [
                    'id' => $row->item_id,
                    'floor' => $row->floor_id,
                    'kind' => $row->kind,
                    'model_kind' => $row->model_kind,
                    'source_shape' => $row->source_shape,
                    'x' => (float) $row->x,
                    'y' => (float) $row->y,
                    'rotation' => (float) $row->rotation,
                    'width' => (float) $row->width,
                    'depth' => (float) $row->depth,
                    'height' => (float) $row->height,
                    'base_z' => (float) $row->base_z,
                ]);
            })
            ->values()
            ->all();
    }

    public function state(): array
    {
        $row = DB::table('furniture_layouts')->find(1);
        $models = [];
        $versions = json_decode(file_get_contents(resource_path('js/model-versions.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach (['ground', 'upper', 'attic'] as $floor) {
            $models[$floor] = $row->model_revision === null
                ? '/models/'.$floor.'.glb?v='.$versions[$floor]
                : '/dashboard/furniture/models/'.$row->model_revision.'/'.$floor;
        }

        return [
            'revision' => $row->revision,
            'model_revision' => $row->model_revision,
            'status' => $row->status,
            'items' => $this->items(),
            'models' => $models,
        ];
    }

    public function save(array $data): array
    {
        DB::transaction(function () use ($data) {
            $row = DB::table('furniture_layouts')->lockForUpdate()->find(1);
            abort_if(in_array($row->status, ['queued', 'building']), 409, 'Er wordt al een plattegrond opgebouwd. Wacht tot deze klaar is.');
            abort_if((int) $row->revision !== $data['revision'], 409, 'De meubels zijn elders gewijzigd. Herlaad de lijst voor je opslaat.');

            $known = DB::table('furniture_items')
                ->get()
                ->mapWithKeys(fn ($item) => [$item->floor_id.':'.$item->item_id => $item]);
            $seen = [];

            foreach ($data['items'] as $index => $item) {
                $key = $item['floor'].':'.$item['id'];
                if (! $known->has($key) || isset($seen[$key])) {
                    throw ValidationException::withMessages(["items.$index.id" => 'Onbekend of dubbel meubelnummer.']);
                }
                $seen[$key] = true;
                $current = $known->get($key);

                if ($item['height'] + $item['base_z'] > 500) {
                    throw ValidationException::withMessages(["items.$index.height" => 'Hoogte plus afstand vanaf de vloer mag maximaal 500 cm zijn.']);
                }

                $metadata = $current->metadata
                    ? json_decode($current->metadata, true, flags: JSON_THROW_ON_ERROR)
                    : [];
                $color = trim((string) ($item['color'] ?? ''));
                if ($color === '') {
                    unset($metadata['color']);
                } else {
                    $metadata['color'] = strtolower($color);
                }

                DB::table('furniture_items')
                    ->where('floor_id', $item['floor'])
                    ->where('item_id', $item['id'])
                    ->update([
                        'x' => array_key_exists('x', $item) ? round($item['x'] / 100, 5) : $current->x,
                        'y' => array_key_exists('y', $item) ? round($item['y'] / 100, 5) : $current->y,
                        'width' => round($item['width'] / 100, 5),
                        'depth' => round($item['depth'] / 100, 5),
                        'height' => round($item['height'] / 100, 5),
                        'base_z' => round($item['base_z'] / 100, 5),
                        'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                    ]);
            }

            DB::table('furniture_layouts')->where('id', 1)->update([
                'revision' => $row->revision + 1,
                'updated_at' => now(),
            ]);
        });

        return $this->state();
    }

    public function build(int $revision): array
    {
        DB::transaction(function () use ($revision) {
            $row = DB::table('furniture_layouts')->lockForUpdate()->find(1);
            abort_if(in_array($row->status, ['queued', 'building']), 409, 'Er wordt al een plattegrond opgebouwd. Wacht tot deze klaar is.');
            abort_if((int) $row->revision !== $revision, 409, 'De meubels zijn elders gewijzigd. Herlaad de lijst voor je de plattegrond opbouwt.');

            DB::table('furniture_layouts')->where('id', 1)->update([
                'status' => 'queued',
                'started_at' => null,
                'updated_at' => now(),
            ]);

            BuildFurniture::dispatch($revision)->onConnection('furniture')->onQueue('furniture');
        });

        return $this->state();
    }
}
