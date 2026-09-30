<?php

namespace App\Http\Controllers;

use App\Models\StorageBox;
use App\Models\StorageBoxAnalysis;
use App\Models\StorageBoxItem;
use App\Services\InventoryVisionAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AtticBoxInventoryController extends Controller
{
    private const LOCATION = 'zolder';

    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));

        $boxes = StorageBox::query()
            ->where('location', self::LOCATION)
            ->withCount('items')
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($builder) use ($query) {
                    if (ctype_digit($query)) {
                        $builder->where('number', (int) $query);
                    }

                    $builder->orWhereHas('items', function ($items) use ($query) {
                        $items->where('name', 'like', '%'.$query.'%')
                            ->orWhere('notes', 'like', '%'.$query.'%');
                    });
                });
            })
            ->orderBy('number')
            ->get();

        return view('inventory.attic-boxes.index', [
            'boxes' => $boxes,
            'query' => $query,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $box = StorageBox::firstOrCreate([
            'location' => self::LOCATION,
            'number' => (int) $data['number'],
        ]);

        return redirect()->route('inventory.attic-boxes.show', $box->number);
    }

    public function show(int $number, InventoryVisionAnalyzer $analyzer): View
    {
        $box = $this->box($number);
        $box->load(['items', 'photos']);

        $latestBatchId = $box->photos->first(fn ($photo) => filled($photo->batch_id))?->batch_id;
        $latestPhotoCount = $latestBatchId
            ? $box->photos->where('batch_id', $latestBatchId)->count()
            : 0;

        return view('inventory.attic-boxes.show', [
            'box' => $box,
            'aiConfigured' => $analyzer->configured(),
            'latestPhotoCount' => $latestPhotoCount,
        ]);
    }

    public function storeItem(Request $request, int $number): RedirectResponse
    {
        $box = $this->box($number);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $box->items()->create($data);
        $box->touch();

        return back()->with('status', 'Item toegevoegd.');
    }

    public function updateItem(Request $request, int $number, StorageBoxItem $item): RedirectResponse
    {
        $box = $this->box($number);
        abort_unless($item->storage_box_id === $box->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $item->update($data);
        $box->touch();

        return back()->with('status', 'Item bijgewerkt.');
    }

    public function destroyItem(int $number, StorageBoxItem $item): RedirectResponse
    {
        $box = $this->box($number);
        abort_unless($item->storage_box_id === $box->id, 404);

        $item->delete();
        $box->touch();

        return back()->with('status', 'Item verwijderd.');
    }

    public function storePhotos(Request $request, int $number): RedirectResponse
    {
        $box = $this->box($number);
        $data = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:4'],
            'photos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
        ]);

        $batchId = (string) Str::uuid();

        foreach ($data['photos'] as $photo) {
            $path = $photo->store('inventory/zolder/kisten/'.$box->number, 'local');
            $box->photos()->create([
                'batch_id' => $batchId,
                'path' => $path,
                'original_name' => $photo->getClientOriginalName(),
            ]);
        }

        $box->touch();

        return back()->with('status', 'Fotoset opgeslagen. Je kunt hem nu met AI analyseren.');
    }

    public function analyze(int $number, InventoryVisionAnalyzer $analyzer): RedirectResponse
    {
        $box = $this->box($number);
        $box->load('items');

        if (! $analyzer->configured()) {
            return back()->withErrors(['ai' => 'De gekozen AI-provider is niet volledig geconfigureerd.']);
        }

        $latestPhoto = $box->photos()->whereNotNull('batch_id')->latest('id')->first();
        if (! $latestPhoto) {
            return back()->withErrors(['photos' => 'Upload eerst een nieuwe fotoset.']);
        }

        $photos = $box->photos()
            ->where('batch_id', $latestPhoto->batch_id)
            ->oldest('id')
            ->get();

        $lock = Cache::lock('inventory-ai-analysis:'.$box->id, 180);

        if (! $lock->get()) {
            return back()->withErrors(['ai' => 'Er loopt al een AI-analyse voor deze kist. Wacht tot die klaar is.']);
        }

        try {
            $result = $analyzer->analyze($box, $photos);

            $analysis = $box->analyses()->create([
                'photo_batch_id' => $latestPhoto->batch_id,
                'model' => $result['model'],
                'response_id' => $result['response_id'],
                'result' => $result['result'],
            ]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['ai' => 'De AI-analyse is mislukt. Er is niets aan de inventaris gewijzigd.']);
        } finally {
            $lock->release();
        }

        return redirect()->route('inventory.attic-boxes.analysis.review', [$box->number, $analysis]);
    }

    public function reviewAnalysis(int $number, StorageBoxAnalysis $analysis): View
    {
        $box = $this->box($number);
        abort_unless($analysis->storage_box_id === $box->id, 404);

        return view('inventory.attic-boxes.analysis', [
            'box' => $box,
            'analysis' => $analysis,
        ]);
    }

    public function applyAnalysis(Request $request, int $number, StorageBoxAnalysis $analysis): RedirectResponse
    {
        $box = $this->box($number);
        abort_unless($analysis->storage_box_id === $box->id, 404);
        abort_if($analysis->applied_at, 409);

        $data = $request->validate([
            'mode' => ['required', 'in:merge,replace'],
            'items' => ['nullable', 'array', 'max:100'],
            'items.*.include' => ['required', 'boolean'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $items = collect($data['items'] ?? [])
            ->filter(fn (array $item) => (bool) $item['include'])
            ->map(fn (array $item) => [
                'name' => trim($item['name']),
                'quantity' => (int) $item['quantity'],
                'notes' => filled($item['notes'] ?? null) ? trim($item['notes']) : null,
            ]);

        DB::transaction(function () use ($box, $analysis, $data, $items) {
            if ($data['mode'] === 'replace') {
                $box->items()->delete();
            }

            foreach ($items as $item) {
                if ($data['mode'] === 'merge') {
                    $existing = $box->items()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($item['name'])])
                        ->first();

                    if ($existing) {
                        $existing->update($item);
                        continue;
                    }
                }

                $box->items()->create($item);
            }

            $analysis->update(['applied_at' => now()]);
            $box->touch();
        });

        return redirect()
            ->route('inventory.attic-boxes.show', $box->number)
            ->with('status', 'AI-voorstel opgeslagen in de inventaris.');
    }

    private function box(int $number): StorageBox
    {
        return StorageBox::query()
            ->where('location', self::LOCATION)
            ->where('number', $number)
            ->firstOrFail();
    }
}
