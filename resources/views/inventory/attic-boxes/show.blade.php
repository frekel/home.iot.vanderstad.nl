@extends('inventory.layout')

@section('title', 'Kist '.$box->number)

@section('content')
<div class="topbar">
    <a href="{{ route('inventory.attic-boxes.index') }}">← Alle kisten</a>
</div>

<h1>Kist {{ $box->number }}</h1>
<p>{{ $box->items->count() }} {{ $box->items->count() === 1 ? 'item' : 'items' }} in de inventaris.</p>

@if (session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="error">{{ $errors->first() }}</div>
@endif

<section class="card">
    <h2>Foto's van de inhoud</h2>
    <p>Maak één of meer foto's van bovenaf. De foto's worden privé opgeslagen en vormen straks de invoer voor de AI-inventarisatie.</p>
    <form method="post" enctype="multipart/form-data" action="{{ route('inventory.attic-boxes.photos.store', $box->number) }}" class="stack">
        @csrf
        <label class="photo-input">
            <span>Foto's kiezen of maken</span>
            <input type="file" name="photos[]" accept="image/*" capture="environment" multiple required>
        </label>
        <button class="button full" type="submit">Foto's opslaan</button>
    </form>
    @if ($box->photos->isNotEmpty())
        <p class="photo-count">{{ $box->photos->count() }} {{ $box->photos->count() === 1 ? 'foto' : "foto's" }} opgeslagen voor deze kist.</p>
    @endif
</section>

<section class="card">
    <h2>Item toevoegen</h2>
    <form method="post" action="{{ route('inventory.attic-boxes.items.store', $box->number) }}" class="stack">
        @csrf
        <label>Naam<input type="text" name="name" required maxlength="255" placeholder="Bijv. HDMI-kabel"></label>
        <label>Aantal<input type="number" name="quantity" value="1" min="1" max="9999" inputmode="numeric" required></label>
        <label>Opmerking<textarea name="notes" maxlength="2000" placeholder="Optioneel"></textarea></label>
        <button class="button full" type="submit">Toevoegen</button>
    </form>
</section>

<section>
    <h2>Inhoud</h2>
    <div class="stack">
        @forelse ($box->items as $item)
            <article class="card item">
                <div class="item-title">
                    <strong>{{ $item->name }}</strong>
                    <span class="qty">× {{ $item->quantity }}</span>
                </div>
                @if ($item->notes)
                    <div class="muted">{{ $item->notes }}</div>
                @endif
                <details>
                    <summary>Bewerken</summary>
                    <form method="post" action="{{ route('inventory.attic-boxes.items.update', [$box->number, $item]) }}" class="stack" style="margin-top:12px">
                        @csrf
                        @method('put')
                        <label>Naam<input type="text" name="name" value="{{ $item->name }}" maxlength="255" required></label>
                        <label>Aantal<input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="9999" inputmode="numeric" required></label>
                        <label>Opmerking<textarea name="notes" maxlength="2000">{{ $item->notes }}</textarea></label>
                        <div class="inline-actions">
                            <button class="button secondary" type="submit">Opslaan</button>
                        </div>
                    </form>
                    <form method="post" action="{{ route('inventory.attic-boxes.items.destroy', [$box->number, $item]) }}" style="margin-top:8px" onsubmit="return confirm('Dit item verwijderen?')">
                        @csrf
                        @method('delete')
                        <button class="button danger full" type="submit">Verwijderen</button>
                    </form>
                </details>
            </article>
        @empty
            <div class="empty card">Deze kist heeft nog geen items.</div>
        @endforelse
    </div>
</section>
@endsection
