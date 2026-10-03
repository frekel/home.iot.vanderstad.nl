@extends('inventory.layout')

@section('title', $box->name ? 'Kist '.$box->number.' · '.$box->name : 'Kist '.$box->number)

@section('content')
<div class="topbar">
    <a href="{{ route('inventory.attic-boxes.index') }}">← Alle kisten</a>
</div>

<h1>Kist {{ $box->number }}</h1>
@if ($box->name)
    <p class="box-name">{{ $box->name }}</p>
@endif
<p>{{ $box->items->count() }} {{ $box->items->count() === 1 ? 'item' : 'items' }} in de inventaris.</p>

@if (session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="error">{{ $errors->first() }}</div>
@endif

<section class="card">
    <h2>Naam van de kist</h2>
    <form method="post" action="{{ route('inventory.attic-boxes.update', $box->number) }}" class="row">
        @csrf
        @method('patch')
        <input type="text" name="name" maxlength="100" value="{{ old('name', $box->name) }}" placeholder="Bijv. Kerstspullen">
        <button class="button secondary" type="submit">Opslaan</button>
    </form>
</section>

<section class="card">
    <h2>Inventariseren met foto's</h2>
    <p>Maak maximaal vier foto's van de inhoud, liefst vanuit verschillende hoeken. Eén upload vormt één fotoset voor de volgende AI-analyse.</p>
    <form method="post" enctype="multipart/form-data" action="{{ route('inventory.attic-boxes.photos.store', $box->number) }}" class="stack">
        @csrf
        <label class="photo-input">
            <span>Foto's kiezen of maken</span>
            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" capture="environment" multiple required>
        </label>
        <button class="button full" type="submit">Foto's opslaan</button>
    </form>

    @if ($latestPhotoCount > 0)
        <p class="photo-count">De nieuwste fotoset bevat {{ $latestPhotoCount }} {{ $latestPhotoCount === 1 ? 'foto' : "foto's" }}.</p>
        <form method="post" action="{{ route('inventory.attic-boxes.analysis.run', $box->number) }}" data-ai-analysis-form>
            @csrf
            <button class="button full" type="submit" data-ai-analysis-button @disabled(! $aiConfigured)>Analyseer nieuwste fotoset met AI</button>
        </form>
        @unless ($aiConfigured)
            <p class="photo-count">AI-analyse is nog niet beschikbaar: de gekozen AI-provider is niet volledig geconfigureerd.</p>
        @endunless
    @elseif ($box->photos->isNotEmpty())
        <p class="photo-count">Er zijn oudere foto's zonder fotoset opgeslagen. Upload een nieuwe fotoset om AI-analyse te starten.</p>
    @endif

    @if ($box->photos->isNotEmpty())
        <h3 class="subheading">Opgeslagen foto's</h3>
        <div class="photo-grid">
            @foreach ($box->photos as $photo)
                <a class="photo-card" href="{{ route('inventory.attic-boxes.photos.show', [$box->number, $photo]) }}" target="_blank" rel="noopener">
                    <img src="{{ route('inventory.attic-boxes.photos.show', [$box->number, $photo]) }}" alt="Foto van kist {{ $box->number }}" loading="lazy">
                    <span>{{ $photo->created_at->format('d-m-Y H:i') }}</span>
                </a>
            @endforeach
        </div>
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

                <details>
                    <summary>Verplaatsen naar andere kist</summary>
                    <form method="post" action="{{ route('inventory.attic-boxes.items.move', [$box->number, $item]) }}" class="stack" style="margin-top:12px">
                        @csrf
                        <label>
                            Naar kist
                            <input type="number" name="target_number" min="1" max="9999" inputmode="numeric" required placeholder="Bijv. 24">
                        </label>
                        <label>
                            Aantal
                            <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="{{ $item->quantity }}" inputmode="numeric" required>
                        </label>
                        <button class="button secondary full" type="submit">Verplaatsen</button>
                    </form>
                </details>
            </article>
        @empty
            <div class="empty card">Deze kist heeft nog geen items.</div>
        @endforelse
    </div>
</section>

<script>
document.querySelectorAll('[data-ai-analysis-form]').forEach((form) => {
    let submitted = false;

    form.addEventListener('submit', (event) => {
        if (submitted) {
            event.preventDefault();
            return;
        }

        submitted = true;

        const button = form.querySelector('[data-ai-analysis-button]');
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.textContent = 'Analyseren...';
        }
    });
});
</script>
@endsection
