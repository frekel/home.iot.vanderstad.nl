<?php

namespace App\Http\Controllers;

use App\Models\StorageBox;
use App\Models\StorageBoxItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

    public function show(int $number): View
    {
        $box = StorageBox::query()
            ->where('location', self::LOCATION)
            ->where('number', $number)
            ->firstOrFail();

        $box->load(['items', 'photos']);

        return view('inventory.attic-boxes.show', ['box' => $box]);
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
            'photos' => ['required', 'array', 'min:1', 'max:8'],
            'photos.*' => ['required', 'image', 'max:12288'],
        ]);

        foreach ($data['photos'] as $photo) {
            $path = $photo->store('inventory/zolder/kisten/'.$box->number, 'local');
            $box->photos()->create([
                'path' => $path,
                'original_name' => $photo->getClientOriginalName(),
            ]);
        }

        $box->touch();

        return back()->with('status', 'Foto'.(count($data['photos']) === 1 ? '' : "'s").' opgeslagen.');
    }

    private function box(int $number): StorageBox
    {
        return StorageBox::query()
            ->where('location', self::LOCATION)
            ->where('number', $number)
            ->firstOrFail();
    }
}
