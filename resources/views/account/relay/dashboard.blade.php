@extends('layouts.app')

@section('title', 'Mon espace relais — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-4xl mx-auto px-4">

        <h1 class="text-3xl font-extrabold text-gray-900">🏪 Mon espace relais</h1>
        <p class="mt-2 text-gray-500">
            Gère les colis Swap'Îles : {{ $points->pluck('name')->implode(', ') }}
        </p>

        @if(session('status'))
            <div class="mt-5 rounded-2xl bg-emerald-50 border border-emerald-100 p-4 text-sm font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        {{-- Solde --}}
        <div class="mt-6 rounded-3xl border border-teal-100 bg-teal-50/50 p-6">
            <p class="text-sm font-semibold text-teal-700">Ton solde Swap'Îles</p>
            <p class="mt-1 text-4xl font-extrabold text-teal-800">{{ number_format($balance, 2, ',', ' ') }} €</p>
            <p class="mt-1 text-sm text-teal-700">{{ $collectedCount }} colis remis · 1 € par colis</p>
        </div>

        {{-- À réceptionner --}}
        <div class="mt-8">
            <h2 class="text-lg font-extrabold text-gray-900">📥 Colis à réceptionner ({{ $toReceive->count() }})</h2>
            <p class="text-sm text-gray-500">Le vendeur t'apporte le colis. Confirme sa réception pour prévenir l'acheteur.</p>

            <div class="mt-3 space-y-3">
                @forelse($toReceive as $t)
                    <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-bold text-gray-900">{{ $t->listing->title ?? 'Article' }}</p>
                                <p class="text-sm text-gray-500">Vendeur : {{ $t->seller->name ?? '—' }} · pour {{ $t->buyer->name ?? '—' }}</p>
                            </div>
                            <form method="POST" action="{{ route('account.relay.deposit', $t) }}" class="shrink-0">
                                @csrf
                                @method('PATCH')
                                <button class="rounded-xl bg-teal-700 hover:bg-teal-800 text-white font-bold px-4 py-2.5 text-sm">
                                    Colis reçu
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="rounded-2xl bg-white border border-gray-100 p-4 text-sm text-gray-400">Aucun colis en attente de dépôt.</p>
                @endforelse
            </div>
        </div>

        {{-- À remettre --}}
        <div class="mt-8">
            <h2 class="text-lg font-extrabold text-gray-900">📤 Colis à remettre ({{ $toHandOver->count() }})</h2>
            <p class="text-sm text-gray-500">L'acheteur vient chercher son colis. Demande-lui son <strong>code de retrait</strong> à 6 caractères.</p>

            <div class="mt-3 space-y-3">
                @forelse($toHandOver as $t)
                    <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-bold text-gray-900">{{ $t->listing->title ?? 'Article' }}</p>
                                <p class="text-sm text-gray-500">Acheteur : {{ $t->buyer->name ?? '—' }}</p>
                            </div>
                            <form method="POST" action="{{ route('account.relay.pickup', $t) }}" class="flex items-end gap-2">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label for="code-{{ $t->id }}" class="block text-xs font-semibold text-gray-600">Code de retrait</label>
                                    <input id="code-{{ $t->id }}" name="pickup_code" required maxlength="12" autocomplete="off"
                                           placeholder="Ex : ABC234"
                                           class="mt-1 w-36 rounded-xl border border-gray-300 px-3 py-2.5 text-sm font-bold uppercase tracking-widest focus:border-teal-500 focus:ring-teal-500 @error('pickup_code') border-red-500 @enderror">
                                </div>
                                <button class="rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2.5 text-sm">
                                    Remettre
                                </button>
                            </form>
                        </div>
                        @error('pickup_code')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>
                @empty
                    <p class="rounded-2xl bg-white border border-gray-100 p-4 text-sm text-gray-400">Aucun colis à remettre pour le moment.</p>
                @endforelse
            </div>
        </div>

        {{-- Historique récent --}}
        @if($recentCollected->isNotEmpty())
            <div class="mt-8">
                <h2 class="text-lg font-extrabold text-gray-900">✅ Colis remis récemment</h2>
                <div class="mt-3 divide-y divide-gray-100 rounded-2xl border border-gray-100 bg-white">
                    @foreach($recentCollected as $t)
                        <div class="flex items-center justify-between gap-3 p-4 text-sm">
                            <span class="min-w-0 truncate text-gray-700">{{ $t->listing->title ?? 'Article' }} — {{ $t->buyer->name ?? '—' }}</span>
                            <span class="shrink-0 font-semibold text-teal-700">+{{ number_format((float) $t->relay_merchant_fee, 2, ',', ' ') }} €</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
@endsection
