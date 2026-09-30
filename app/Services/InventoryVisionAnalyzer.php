<?php

namespace App\Services;

use App\Models\StorageBox;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class InventoryVisionAnalyzer
{
    public function configured(): bool
    {
        return match ($this->provider()) {
            'cloudflare' => filled(config('services.cloudflare.account_id'))
                && filled(config('services.cloudflare.api_token')),
            'openai' => filled(config('services.openai.api_key')),
            default => false,
        };
    }

    public function provider(): string
    {
        return strtolower((string) config('services.inventory_ai.provider', 'cloudflare'));
    }

    public function analyze(StorageBox $box, Collection $photos): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('De gekozen AI-provider is niet volledig geconfigureerd.');
        }

        if ($photos->isEmpty()) {
            throw new RuntimeException('Er zijn geen foto’s aangeleverd voor analyse.');
        }

        return match ($this->provider()) {
            'cloudflare' => $this->analyzeWithCloudflare($box, $photos),
            'openai' => $this->analyzeWithOpenAI($box, $photos),
            default => throw new RuntimeException('Onbekende inventory AI-provider: '.$this->provider()),
        };
    }

    private function analyzeWithCloudflare(StorageBox $box, Collection $photos): array
    {
        $visionModel = (string) config('services.cloudflare.inventory_vision_model', '@cf/meta/llama-3.2-11b-vision-instruct');
        $consolidationModel = (string) config('services.cloudflare.inventory_consolidation_model', '@cf/meta/llama-3.3-70b-instruct-fp8-fast');
        $photoResults = [];

        foreach ($photos as $index => $photo) {
            $mime = $this->supportedMime($photo->path);
            $image = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($photo->path));

            $response = $this->cloudflareRequest($visionModel, [
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Je analyseert foto’s van de inhoud van een opslagkist. Noem uitsluitend voorwerpen die daadwerkelijk zichtbaar zijn. Verzin niets. Geef korte Nederlandse namen. Geef uitsluitend geldige JSON terug, zonder markdown of uitleg.',
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Analyseer foto '.($index + 1).' van kist '.$box->number.'. Retourneer exact dit JSON-formaat: {"items":[{"name":"...","quantity":1,"notes":"","confidence":"high|medium|low"}],"warnings":["..."]}. Tel alleen wat op deze foto zichtbaar is. Als een aantal onzeker is, gebruik een voorzichtige schatting en lagere confidence.',
                    ],
                ],
                'image' => $image,
                'temperature' => 0,
                'max_tokens' => 1400,
            ]);

            $photoResults[] = $this->parseCloudflareResult($response, 'vision');
        }

        if (count($photoResults) === 1) {
            $result = $this->normalizeResult($photoResults[0]);
        } else {
            $result = $this->consolidateCloudflareResults($box, $photoResults, $consolidationModel);
        }

        return [
            'model' => 'cloudflare:'.$visionModel,
            'response_id' => null,
            'result' => $result,
        ];
    }

    private function consolidateCloudflareResults(StorageBox $box, array $photoResults, string $model): array
    {
        $existing = $box->items
            ->map(fn ($item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'notes' => $item->notes,
            ])
            ->values()
            ->all();

        $response = $this->cloudflareRequest($model, [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Je consolideert meerdere AI-observaties van dezelfde opslagkist. Hetzelfde fysieke voorwerp kan op meerdere foto’s staan: tel dat niet dubbel. Verzin geen voorwerpen. Gebruik korte Nederlandse namen.',
                ],
                [
                    'role' => 'user',
                    'content' => 'Kist '.$box->number.'. Bestaande inventaris is alleen naamcontext en geen bewijs dat iets zichtbaar is: '.json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'. Foto-observaties: '.json_encode($photoResults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'. Maak één geconsolideerde lijst.',
                ],
            ],
            'temperature' => 0,
            'max_tokens' => 1800,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => $this->inventorySchema(),
            ],
        ]);

        return $this->normalizeResult($this->parseCloudflareResult($response, 'consolidation'));
    }

    private function cloudflareRequest(string $model, array $payload): Response
    {
        $baseUrl = rtrim((string) config('services.cloudflare.base_url', 'https://api.cloudflare.com/client/v4'), '/');
        $accountId = (string) config('services.cloudflare.account_id');
        $url = $baseUrl.'/accounts/'.$accountId.'/ai/run/'.$model;

        $response = Http::withToken((string) config('services.cloudflare.api_token'))
            ->acceptJson()
            ->asJson()
            ->timeout(120)
            ->post($url, $payload);

        if ($response->failed() || $response->json('success') === false) {
            $code = $response->json('errors.0.code');
            $message = $response->json('errors.0.message');
            $detail = trim(implode(' ', array_filter([
                $code ? 'code '.$code : null,
                is_string($message) ? $message : null,
            ])));

            throw new RuntimeException(
                'Cloudflare Workers AI fout (HTTP '.$response->status().')'.($detail !== '' ? ': '.$detail : '.')
            );
        }

        return $response;
    }

    private function parseCloudflareResult(Response $response, string $stage): array
    {
        $value = $response->json('result.response');

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException('Cloudflare gaf geen bruikbaar resultaat terug tijdens '.$stage.'.');
        }

        $text = trim($value);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text) ?? $text;

        try {
            $decoded = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Cloudflare gaf ongeldige JSON terug tijdens '.$stage.'.', previous: $e);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Cloudflare gaf een onverwacht resultaat terug tijdens '.$stage.'.');
        }

        return $decoded;
    }

    private function analyzeWithOpenAI(StorageBox $box, Collection $photos): array
    {
        $content = [[
            'type' => 'input_text',
            'text' => $this->prompt($box),
        ]];

        foreach ($photos as $photo) {
            $mime = $this->supportedMime($photo->path);
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
                        'schema' => $this->inventorySchema(),
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

        return [
            'model' => (string) ($response->json('model') ?: $model),
            'response_id' => $response->json('id'),
            'result' => $this->normalizeResult($result),
        ];
    }

    private function supportedMime(string $path): string
    {
        $mime = Storage::disk('local')->mimeType($path);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            throw new RuntimeException('Niet-ondersteund afbeeldingstype voor AI-analyse.');
        }

        return $mime;
    }

    private function normalizeResult(array $result): array
    {
        if (! isset($result['items']) || ! is_array($result['items'])) {
            throw new RuntimeException('AI-resultaat bevat geen geldige itemlijst.');
        }

        return [
            'items' => collect($result['items'])
                ->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null))
                ->map(fn (array $item) => [
                    'name' => trim((string) $item['name']),
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                    'notes' => trim((string) ($item['notes'] ?? '')),
                    'confidence' => in_array(($item['confidence'] ?? null), ['high', 'medium', 'low'], true)
                        ? $item['confidence']
                        : 'low',
                ])
                ->values()
                ->all(),
            'warnings' => collect($result['warnings'] ?? [])
                ->filter(fn ($warning) => is_string($warning) && trim($warning) !== '')
                ->map(fn ($warning) => trim($warning))
                ->values()
                ->all(),
        ];
    }

    private function inventorySchema(): array
    {
        return [
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
