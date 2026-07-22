@extends('layouts.app')

@section('title', 'Proposer un échange — Swap\'Îles')

@section('content')
@php
    $inp = 'w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-100';
    $lbl = 'mb-1.5 block text-sm font-semibold text-gray-800';
    $hasListings = $myListings->count() > 0;
@endphp

<section class="bg-gray-50 min-h-screen py-6 sm:py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6">

        <div class="mb-5">
            <a href="{{ route('listings.show', $listing) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">← Retour à l'annonce</a>
            <h1 class="mt-2 text-2xl font-extrabold text-gray-900">🔄 Proposer un échange</h1>
        </div>

        {{-- Ce que je veux --}}
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm mb-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Vous souhaitez</p>
            <div class="mt-2 flex items-center gap-3">
                <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-gray-100">
                    @if($listing->images->first())
                        <img src="{{ $listing->images->first()->url }}" class="h-full w-full object-cover" alt="">
                    @else
                        <div class="grid h-full w-full place-items-center text-2xl text-gray-300">📦</div>
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="truncate font-bold text-gray-900">{{ $listing->title }}</p>
                    <p class="text-sm text-gray-500">Vendeur : {{ $listing->user->name ?? '—' }}</p>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="mb-5 rounded-2xl bg-red-50 border border-red-200 text-red-800 p-4 text-sm font-semibold">
                @foreach($errors->all() as $error)<p>⚠️ {{ $error }}</p>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('exchange.store', $listing) }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            {{-- Choix du mode --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <p class="{{ $lbl }}">Que proposez-vous en échange&nbsp;?</p>
                <div class="grid grid-cols-2 gap-2 rounded-xl bg-gray-100 p-1" role="tablist">
                    <button type="button" data-mode-tab="listing" class="ex-tab rounded-lg px-3 py-2 text-sm font-bold transition">📦 Une de mes annonces</button>
                    <button type="button" data-mode-tab="custom" class="ex-tab rounded-lg px-3 py-2 text-sm font-bold transition">✏️ Un autre article</button>
                </div>
                <input type="hidden" name="mode" id="mode-input" value="{{ old('mode', $hasListings ? 'listing' : 'custom') }}">
            </div>

            {{-- Mode : mes annonces --}}
            <div id="mode-listing" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <p class="{{ $lbl }}">Choisissez l'annonce à proposer</p>
                @if($hasListings)
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($myListings as $ml)
                            <label class="cursor-pointer">
                                <input type="radio" name="offered_listing_id" value="{{ $ml->id }}" class="peer sr-only" @checked(old('offered_listing_id') == $ml->id)>
                                <div class="rounded-xl border-2 border-gray-200 p-1.5 transition peer-checked:border-teal-500 peer-checked:ring-2 peer-checked:ring-teal-100">
                                    <div class="aspect-square overflow-hidden rounded-lg bg-gray-100">
                                        @if($ml->images->first())
                                            <img src="{{ $ml->images->first()->url }}" class="h-full w-full object-cover" alt="">
                                        @else
                                            <div class="grid h-full w-full place-items-center text-2xl text-gray-300">📦</div>
                                        @endif
                                    </div>
                                    <p class="mt-1 truncate text-xs font-semibold text-gray-800">{{ $ml->title }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                @else
                    <p class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500">
                        Vous n'avez pas encore d'annonce en ligne. Utilisez plutôt « Un autre article » ci-dessus,
                        ou <a href="{{ route('account.listings.create') }}" class="font-semibold text-teal-700">déposez une annonce</a>.
                    </p>
                @endif
            </div>

            {{-- Mode : article libre --}}
            <div id="mode-custom" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-4">
                <div>
                    <label for="offered_title" class="{{ $lbl }}">Nom de l'article</label>
                    <input id="offered_title" type="text" name="offered_title" value="{{ old('offered_title') }}" placeholder="Ex : Sac à main en cuir" class="{{ $inp }}">
                </div>
                <div>
                    <label for="offered_condition" class="{{ $lbl }}">État</label>
                    <select id="offered_condition" name="offered_condition" class="{{ $inp }}">
                        <option value="">Non précisé</option>
                        <option value="Neuf avec étiquette" @selected(old('offered_condition') === 'Neuf avec étiquette')>Neuf avec étiquette</option>
                        <option value="Neuf sans étiquette" @selected(old('offered_condition') === 'Neuf sans étiquette')>Neuf sans étiquette</option>
                        <option value="Très bon état" @selected(old('offered_condition') === 'Très bon état')>Très bon état</option>
                        <option value="Bon état" @selected(old('offered_condition') === 'Bon état')>Bon état</option>
                        <option value="Satisfaisant" @selected(old('offered_condition') === 'Satisfaisant')>Satisfaisant</option>
                    </select>
                </div>
                <div>
                    <label for="offered_description" class="{{ $lbl }}">Description</label>
                    <textarea id="offered_description" name="offered_description" rows="3" placeholder="Marque, taille, détails…" class="{{ $inp }}">{{ old('offered_description') }}</textarea>
                </div>
                <div>
                    <label for="photo" class="{{ $lbl }}">Photo (optionnelle)</label>
                    <input id="photo" type="file" name="photo" accept="image/*" class="{{ $inp }}">
                    <p class="mt-1 text-xs text-gray-500">Une photo augmente vos chances que l'échange soit accepté.</p>
                </div>
            </div>

            {{-- Message --}}
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <label for="message" class="{{ $lbl }}">Message au vendeur (optionnel)</label>
                <textarea id="message" name="message" rows="2" placeholder="Bonjour, je vous propose cet échange…" class="{{ $inp }}">{{ old('message') }}</textarea>
            </div>

            <button class="w-full rounded-xl bg-teal-600 px-6 py-4 font-semibold text-white shadow-sm transition hover:bg-teal-700">
                Envoyer ma proposition d'échange
            </button>
        </form>
    </div>
</section>

<script>
(function () {
    const modeInput = document.getElementById('mode-input');
    const tabs = document.querySelectorAll('[data-mode-tab]');
    const paneListing = document.getElementById('mode-listing');
    const paneCustom = document.getElementById('mode-custom');

    function apply(mode) {
        modeInput.value = mode;
        tabs.forEach(function (t) {
            const on = t.dataset.modeTab === mode;
            t.classList.toggle('bg-white', on);
            t.classList.toggle('text-teal-700', on);
            t.classList.toggle('shadow-sm', on);
            t.classList.toggle('text-gray-500', !on);
        });
        paneListing.style.display = mode === 'listing' ? 'block' : 'none';
        paneCustom.style.display = mode === 'custom' ? 'block' : 'none';
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function () { apply(t.dataset.modeTab); });
    });

    apply(modeInput.value || 'listing');
})();
</script>
@endsection
