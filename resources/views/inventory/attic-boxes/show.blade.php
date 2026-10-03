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
            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" capture="environment" multiple required data-photo-input>
        </label>
        <button class="button full" type="submit">Foto's opslaan</button>
    </form>

    @if ($latestPhotoCount > 0)
        <p class="photo-count">De nieuwste fotoset bevat {{ $latestPhotoCount }} {{ $latestPhotoCount === 1 ? 'foto' : "foto's" }}.</p>
        <form method="post" action="{{ route('inventory.attic-boxes.analysis.run', $box->number) }}" data-ai-analysis-form>
            @csrf
            <button class="button full" type="submit" data-ai-analysis-button data-ai-configured="{{ $aiConfigured ? '1' : '0' }}" @disabled(! $aiConfigured)>Analyseer nieuwste fotoset met AI</button>
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
                <div class="photo-card">
                    <a href="{{ route('inventory.attic-boxes.photos.show', [$box->number, $photo]) }}" target="_blank" rel="noopener">
                        <img src="{{ route('inventory.attic-boxes.photos.show', [$box->number, $photo]) }}" alt="Foto van kist {{ $box->number }}" loading="lazy">
                    </a>
                    <span>{{ $photo->created_at->format('d-m-Y H:i') }}</span>
                    <form method="post" action="{{ route('inventory.attic-boxes.photos.destroy', [$box->number, $photo]) }}" onsubmit="return confirm('Deze foto definitief verwijderen?')">
                        @csrf
                        @method('delete')
                        <button class="button danger full" type="submit">Foto verwijderen</button>
                    </form>
                </div>
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
                <div class="item-header">
                    <strong>{{ $item->name }} ({{ $item->quantity }})</strong>
                    <div class="item-actions">
                        <button class="button secondary compact" type="button" data-item-toggle="edit-{{ $item->id }}">Bewerken</button>
                        <button class="button secondary compact" type="button" data-item-toggle="move-{{ $item->id }}">Verplaatsen</button>
                    </div>
                </div>

                @if ($item->notes)
                    <div class="muted">{{ $item->notes }}</div>
                @endif

                <div id="edit-{{ $item->id }}" class="item-panel" hidden>
                    <form method="post" action="{{ route('inventory.attic-boxes.items.update', [$box->number, $item]) }}" class="stack">
                        @csrf
                        @method('put')
                        <label>Naam<input type="text" name="name" value="{{ $item->name }}" maxlength="255" required></label>
                        <label>Aantal<input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="9999" inputmode="numeric" required></label>
                        <label>Opmerking<textarea name="notes" maxlength="2000">{{ $item->notes }}</textarea></label>
                        <button class="button secondary full" type="submit">Opslaan</button>
                    </form>
                    <form method="post" action="{{ route('inventory.attic-boxes.items.destroy', [$box->number, $item]) }}" style="margin-top:8px" onsubmit="return confirm('Dit item verwijderen?')">
                        @csrf
                        @method('delete')
                        <button class="button danger full" type="submit">Verwijderen</button>
                    </form>
                </div>

                <div id="move-{{ $item->id }}" class="item-panel" hidden>
                    <form method="post" action="{{ route('inventory.attic-boxes.items.move', [$box->number, $item]) }}" class="stack">
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
                </div>
            </article>
        @empty
            <div class="empty card">Deze kist heeft nog geen items.</div>
        @endforelse
    </div>
</section>

<script>
document.querySelectorAll('[data-item-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const item = button.closest('.item');
        const target = document.getElementById(button.dataset.itemToggle);

        item.querySelectorAll('.item-panel').forEach((panel) => {
            if (panel !== target) {
                panel.hidden = true;
            }
        });

        target.hidden = ! target.hidden;
    });
});

const photoInput = document.querySelector('[data-photo-input]');
const aiButton = document.querySelector('[data-ai-analysis-button]');

if (photoInput && aiButton) {
    const syncAiButton = () => {
        const hasUnsavedPhotos = photoInput.files && photoInput.files.length > 0;
        const aiConfigured = aiButton.dataset.aiConfigured === '1';

        aiButton.disabled = hasUnsavedPhotos || ! aiConfigured;
        aiButton.textContent = hasUnsavedPhotos
            ? "Sla eerst de nieuwe foto's op"
            : 'Analyseer nieuwste fotoset met AI';
    };

    photoInput.addEventListener('change', syncAiButton);
    syncAiButton();
}

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
