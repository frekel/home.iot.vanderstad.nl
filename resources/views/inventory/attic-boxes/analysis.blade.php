@extends('inventory.layout')

@section('title', 'AI-voorstel · Kist '.$box->number)

@section('content')
<div class="topbar">
    <a href="{{ route('inventory.attic-boxes.show', $box->number) }}">← Kist {{ $box->number }}</a>
</div>

<h1>AI-voorstel</h1>
<p>Controleer wat er op de foto's is herkend. Niets wordt in de inventaris gewijzigd totdat je dit formulier opslaat.</p>

@if ($analysis->applied_at)
    <div class="status">Dit AI-voorstel is al toegepast op {{ $analysis->applied_at->format('d-m-Y H:i') }}.</div>
@endif

@if ($errors->any())
    <div class="error">{{ $errors->first() }}</div>
@endif

@php($warnings = $analysis->result['warnings'] ?? [])
@if (count($warnings))
<section class="card">
    <h2>Let op</h2>
    <ul>
        @foreach ($warnings as $warning)
            <li>{{ $warning }}</li>
        @endforeach
    </ul>
</section>
@endif

<form method="post" action="{{ route('inventory.attic-boxes.analysis.apply', [$box->number, $analysis]) }}" class="stack">
    @csrf

    <section class="card stack">
        <h2>Hoe opslaan?</h2>
        <label class="choice">
            <input type="radio" name="mode" value="merge" checked>
            <span><strong>Inventaris bijwerken</strong><small>Items met dezelfde naam worden bijgewerkt. Bestaande items die niet in dit voorstel staan blijven behouden.</small></span>
        </label>
        <label class="choice">
            <input type="radio" name="mode" value="replace">
            <span><strong>Inventaris vervangen</strong><small>De huidige inhoud van kist {{ $box->number }} wordt volledig vervangen door de aangevinkte items hieronder.</small></span>
        </label>
    </section>

    <section>
        <h2>Gevonden items</h2>
        <div class="stack">
            @forelse (($analysis->result['items'] ?? []) as $index => $item)
                <article class="card stack">
                    <label class="choice">
                        <input type="hidden" name="items[{{ $index }}][include]" value="0">
                        <input type="checkbox" name="items[{{ $index }}][include]" value="1" checked>
                        <span><strong>Meenemen</strong><small class="confidence">Betrouwbaarheid: {{ $item['confidence'] ?? 'onbekend' }}</small></span>
                    </label>
                    <label>Naam<input type="text" name="items[{{ $index }}][name]" value="{{ $item['name'] }}" maxlength="255" required></label>
                    <label>Aantal<input type="number" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] }}" min="1" max="9999" inputmode="numeric" required></label>
                    <label>Opmerking<textarea name="items[{{ $index }}][notes]" maxlength="2000">{{ $item['notes'] ?? '' }}</textarea></label>
                </article>
            @empty
                <div class="empty card">De AI heeft geen herkenbare items gevonden. Je kunt hiermee wel de inventaris leegmaken door hieronder voor ‘Inventaris vervangen’ te kiezen.</div>
            @endforelse
        </div>
    </section>

    <button class="button full" type="submit" @disabled($analysis->applied_at)>Bevestigen en opslaan</button>
</form>
@endsection
