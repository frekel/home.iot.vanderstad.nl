<?php

namespace App\Services;

use App\Models\StorageBox;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class InventoryVisionAnalyzer
{
    public function configured(): bool
    {
        return filled(config('services.openai.api_key'));
    }

    public function analyze(StorageBox $box, Collection $photos): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('OpenAI is not configured.');
        }

        if ($photos->isEmpty()) {
            throw new RuntimeException('No photos were supplied for analysis.');
        }

        $content = [[
            'type' => 'input_text',
            'text' => $this->prompt($box),
        ]];

        foreach ($photos as $photo) {
            $mime = Storage::disk('local')->mimeType($photo->path);
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                throw new RuntimeException('Unsupported image type for OpenAI vision.');
            }

            $content[] = [
                'type' => 'input_image',
                'image_url' => 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($photo->path)),
                'detail' => 'high',
            ];
        }

        $model = (string) config('services.openai.inventory_model', 'gpt-5.6-terra');
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        $response = Http::withToken((string) config('services.openai.api_key'))
            ->acceptJson()
            ->timeout(120)
            ->post($baseUrl.'/responses', [
                'model' => $model,
                'instructions' => 'Je analyseert foto’s van de inhoud van een opslagkist. Wees conservatief: verzin niets dat niet zichtbaar is. Combineer hetzelfde object dat op meerdere foto’s staat en tel het niet dubbel. Geef Nederlandse, korte itemnamen. Als het exacte aantal niet betrouwbaar zichtbaar is, geef de beste voorzichtige schatting en verlaag confidence. De bestaande inventaris is alleen context voor consistente namen; neem een bestaand item niet op tenzij het op de nieuwe foto’s zichtbaar is.',
                'input' => [[
                    'role' => 'user',
                    'content' => $content,
                ]],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'storage_box_inventory',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'items' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                            'quantity' => ['type' => 'integer', 'minimum' => 1],
                                            'notes' => ['type' => 'string'],
                                            'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                                        ],
                                        'required' => ['name', 'quantity', 'notes', 'confidence'],
                                        'additionalProperties' => false,
                                    ],
                                ],
                                'warnings' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                            ],
                            'required' => ['items', 'warnings'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI inventory analysis failed with HTTP '.$response->status().'.');
        }

        $outputText = collect($response->json('output', []))
            ->flatMap(fn (array $output) => $output['content'] ?? [])
            ->first(fn (array $item) => ($item['type'] ?? null) === 'output_text');

        $text = $outputText['text'] ?? null;
        if (! is_string($text) || $text === '') {
            throw new RuntimeException('OpenAI returned no structured inventory result.');
        }

        try {
            $result = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('OpenAI returned invalid inventory JSON.', previous: $e);
        }

        if (! is_array($result) || ! isset($result['items'], $result['warnings'])) {
            throw new RuntimeException('OpenAI returned an incomplete inventory result.');
        }

        return [
            'model' => (string) ($response->json('model') ?: $model),
            'response_id' => $response->json('id'),
            'result' => $result,
        ];
    }

    private function prompt(StorageBox $box): string
    {
        $existing = $box->items
            ->map(fn ($item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'notes' => $item->notes,
            ])
            ->values()
            ->all();

        return 'Maak één geconsolideerde inventaris van alles wat zichtbaar is op deze foto’s van kist '.$box->number.'. Bestaande inventaris ter referentie: '.json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
