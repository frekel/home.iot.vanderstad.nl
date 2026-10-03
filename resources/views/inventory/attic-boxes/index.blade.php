@extends('inventory.layout')

@section('title', 'Kisten op zolder')

@section('content')
<div class="topbar">
    <a href="/">← Home</a>
</div>

<h1>Kisten op zolder</h1>
<p>Scan de algemene QR-code, vul het nummer op de kist in en open de inventaris.</p>

@if (session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="error">{{ $errors->first() }}</div>
@endif

<section class="card">
    <form method="post" action="{{ route('inventory.attic-boxes.open') }}" class="stack">
        @csrf
        <label>
            Kistnummer
            <input class="number-entry" type="number" name="number" min="1" max="9999" inputmode="numeric" pattern="[0-9]*" required autofocus placeholder="23" value="{{ old('number') }}">
        </label>
        <button class="button full" type="submit">Open kist</button>
    </form>
</section>

<section class="card">
    <h2>Zoeken in alle kisten</h2>
    <form method="get" action="{{ route('inventory.attic-boxes.index') }}" class="row">
        <input type="search" name="q" value="{{ $query }}" placeholder="Bijv. HDMI-kabel of 23">
        <button class="button secondary" type="submit">Zoek</button>
    </form>
</section>

@if ($query !== '')
    <p>{{ $boxes->count() }} {{ $boxes->count() === 1 ? 'resultaat' : 'resultaten' }} voor “{{ $query }}”.</p>
@elseif ($boxes->isNotEmpty())
    <p>{{ $boxes->count() }} {{ $boxes->count() === 1 ? 'kist' : 'kisten' }} geregistreerd.</p>
@endif

<div class="box-list">
    @forelse ($boxes as $box)
        <a class="box-link" href="{{ route('inventory.attic-boxes.show', $box->number) }}">
            <div>
                <div class="box-number">
                    Kist {{ $box->number }}@if ($box->name): {{ $box->name }}@endif
                </div>
                <div class="muted">{{ $box->items_count }} {{ $box->items_count === 1 ? 'item' : 'items' }}</div>
            </div>
            <span aria-hidden="true">›</span>
        </a>
    @empty
        @if ($query !== '')
            <div class="empty">Niets gevonden.</div>
        @endif
    @endforelse
</div>
@endsection
