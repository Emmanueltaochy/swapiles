@extends('layouts.app')

@section('title', 'Mes ventes pro — Swap’Îles')
@section('robots', 'noindex, nofollow')

@section('content')
@php
    $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €';
    $moisPrecedent = $mois->copy()->subMonth()->format('Y-m');
    $moisSuivant = $mois->copy()->addMonth();
@endphp
<div class="mx-auto max-w-4xl space-y-6 px-4 py-8">
    <div>
        <p class="text-sm font-semibold text-teal-700">🏬 Espace professionnel</p>
        <h1 class="text-2xl font-extrabold text-gray-900">{{ $pro->pro_raison_sociale }}</h1>
        <p class="text-sm text-gray-500">SIRET {{ \App\Support\ComptePro::siret($pro->pro_siret) }} · commission Swap’Îles {{ str_replace('.', ',', round($taux * 100, 2)) }} %</p>
    </div>

    @if(session('status'))
        <div class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    {{-- À faire --}}
    @if($aRetirer->isNotEmpty() || $aExpedier->isNotEmpty())
        <section class="rounded-3xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="font-extrabold text-gray-900">À faire</h2>
            <div class="mt-3 space-y-2">
                @foreach($aRetirer as $t)
                    <a href="{{ route('account.transactions.show', $t) }}" class="flex items-center gap-3 rounded-2xl bg-white p-3 shadow-sm">
                        <span class="text-xl" aria-hidden="true">🏬</span>
                        <span class="min-w-0 flex-1"><span class="block truncate font-semibold text-gray-900">{{ $t->listing?->title }}</span><span class="text-xs text-gray-500">Retrait en boutique · {{ $t->buyer?->name }}</span></span>
                        <span class="shrink-0 text-sm font-bold text-teal-700">Valider le code ›</span>
                    </a>
                @endforeach
                @foreach($aExpedier as $t)
                    <a href="{{ route('account.transactions.show', $t) }}" class="flex items-center gap-3 rounded-2xl bg-white p-3 shadow-sm">
                        <span class="text-xl" aria-hidden="true">🚚</span>
                        <span class="min-w-0 flex-1"><span class="block truncate font-semibold text-gray-900">{{ $t->listing?->title }}</span><span class="text-xs text-gray-500">À livrer · {{ $t->shipping_city }}</span></span>
                        <span class="shrink-0 text-sm font-bold text-teal-700">Expédier ›</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Mois --}}
    <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-2">
            <a href="?mois={{ $moisPrecedent }}" class="grid h-9 w-9 place-items-center rounded-full bg-gray-100 text-gray-700" aria-label="Mois précédent">‹</a>
            <h2 class="font-extrabold text-gray-900">{{ ucfirst($mois->locale('fr')->translatedFormat('F Y')) }}</h2>
            @if($moisSuivant->lte(now()))
                <a href="?mois={{ $moisSuivant->format('Y-m') }}" class="grid h-9 w-9 place-items-center rounded-full bg-gray-100 text-gray-700" aria-label="Mois suivant">›</a>
            @else
                <span class="h-9 w-9"></span>
            @endif
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl bg-gray-50 p-3"><p class="text-xs font-semibold text-gray-500">Ventes</p><p class="text-xl font-extrabold text-gray-900">{{ $totaux['nombre'] }}</p></div>
            <div class="rounded-2xl bg-gray-50 p-3"><p class="text-xs font-semibold text-gray-500">Chiffre d’affaires TTC</p><p class="text-xl font-extrabold text-gray-900">{{ $eur($totaux['chiffre']) }}</p></div>
            <div class="rounded-2xl bg-gray-50 p-3"><p class="text-xs font-semibold text-gray-500">Commission</p><p class="text-xl font-extrabold text-gray-900">{{ $eur($totaux['commission']) }}</p></div>
            <div class="rounded-2xl bg-teal-50 p-3"><p class="text-xs font-semibold text-teal-700">Net pour vous</p><p class="text-xl font-extrabold text-teal-800">{{ $eur($totaux['net']) }}</p><p class="text-[11px] text-teal-700">dont {{ $eur($totaux['verse']) }} déjà versés</p></div>
        </div>

        <div class="mt-5 divide-y divide-gray-100">
            @forelse($ventes as $t)
                <div class="flex items-center gap-3 py-3">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('account.transactions.show', $t) }}" class="block truncate font-semibold text-gray-900">{{ $t->listing?->title ?? 'Article' }}</a>
                        <p class="text-xs text-gray-500">{{ $t->created_at->format('d/m/Y') }} · {{ $t->buyer?->name }} ·
                            {{ match ($t->delivery_method) { 'hand_delivery' => 'Retrait boutique', 'livraison_pro' => 'Livraison', 'colissimo' => 'Colissimo', 'relay' => 'Point relais', default => $t->delivery_method } }}
                            · {{ $t->released_at ? 'Versé ✅' : ($t->status === 'completed' ? 'Versement en cours' : 'En cours') }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-extrabold text-gray-900">{{ $eur($t->seller_amount) }}</p>
                        <a href="{{ route('transactions.bon-de-commande', $t) }}" class="text-xs font-semibold text-teal-700 underline">Bon de commande</a>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-500">Aucune vente ce mois-ci.</p>
            @endforelse
        </div>
    </section>

    <p class="text-xs text-gray-500">Rappel : en tant que vendeur professionnel, vous émettez la facture de chaque vente à votre client (le bon de commande vous donne toutes les informations). Swap’Îles vous facture sa commission.</p>
</div>
@endsection
