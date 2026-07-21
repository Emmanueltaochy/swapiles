@extends('layouts.app')

@section('title', 'Mes transactions — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-6xl mx-auto px-4">

        @php
            $allTransactions = $purchases->concat($sales);

            $toShipCount = $sales
                ->where('status', 'paid')
                ->where('shipping_status', 'pending')
                ->count();

            $inTransitCount = $allTransactions
                ->where('shipping_status', 'shipped')
                ->count();

            $toConfirmCount = $purchases
                ->where('status', 'paid')
                ->where('shipping_status', 'shipped')
                ->count();

            $completedCount = $allTransactions
                ->where('status', 'completed')
                ->count();
        @endphp

        @if(session('status'))
            <div class="mb-6 rounded-2xl bg-teal-50 border border-teal-100 text-teal-800 p-4 text-sm font-semibold">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900">Mes transactions</h1>
                <p class="text-gray-500 mt-2">Suivez vos achats, ventes, expéditions et paiements en un coup d'œil.</p>
            </div>

            <a href="{{ route('account.dashboard') }}" class="inline-flex items-center gap-2 bg-white border border-gray-100 text-gray-800 font-bold px-5 py-3 rounded-2xl shadow-sm hover:bg-gray-50 transition">
                ← Retour au compte
            </a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-8">
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-2xl" aria-hidden="true">📦</p>
                <p class="text-3xl font-extrabold text-orange-600 mt-1">{{ $toShipCount }}</p>
                <p class="text-sm text-gray-500 mt-1">À expédier</p>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-2xl" aria-hidden="true">🚚</p>
                <p class="text-3xl font-extrabold text-blue-600 mt-1">{{ $inTransitCount }}</p>
                <p class="text-sm text-gray-500 mt-1">En livraison</p>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-2xl" aria-hidden="true">✅</p>
                <p class="text-3xl font-extrabold text-purple-600 mt-1">{{ $toConfirmCount }}</p>
                <p class="text-sm text-gray-500 mt-1">À confirmer</p>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-2xl" aria-hidden="true">🎉</p>
                <p class="text-3xl font-extrabold text-green-600 mt-1">{{ $completedCount }}</p>
                <p class="text-sm text-gray-500 mt-1">Terminées</p>
            </div>
        </div>

        <div class="mt-6 flex items-start gap-3 rounded-2xl border border-teal-100 bg-teal-50 p-4">
            <span class="text-xl" aria-hidden="true">🛡️</span>
            <p class="text-sm text-teal-900">
                <span class="font-bold">Paiement protégé.</span>
                Pour les achats par CB, l'argent n'est versé au vendeur qu'une fois la réception confirmée. Chaque étape est suivie ci-dessous&nbsp;: <span class="font-semibold">Paiement → Expédié → Reçu → Virement</span>.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <h2 class="text-xl font-extrabold mb-4 flex items-center gap-2">🛍️ Mes achats
                    <span class="text-xs font-bold text-gray-400">({{ $purchases->count() }})</span>
                </h2>

                @forelse($purchases as $transaction)
                    @include('account.transactions.partials.card', ['transaction' => $transaction, 'type' => 'purchase'])
                @empty
                    <div class="py-10 text-center">
                        <div class="text-4xl" aria-hidden="true">🛍️</div>
                        <p class="mt-3 font-semibold text-gray-900">Aucun achat pour le moment</p>
                        <a href="{{ route('search') }}" class="mt-4 inline-flex rounded-2xl bg-teal-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-teal-700 transition">Explorer les annonces</a>
                    </div>
                @endforelse
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <h2 class="text-xl font-extrabold mb-4 flex items-center gap-2">💰 Mes ventes
                    <span class="text-xs font-bold text-gray-400">({{ $sales->count() }})</span>
                </h2>

                @forelse($sales as $transaction)
                    @include('account.transactions.partials.card', ['transaction' => $transaction, 'type' => 'sale'])
                @empty
                    <div class="py-10 text-center">
                        <div class="text-4xl" aria-hidden="true">💰</div>
                        <p class="mt-3 font-semibold text-gray-900">Aucune vente pour le moment</p>
                        <a href="{{ route('account.listings.create') }}" class="mt-4 inline-flex rounded-2xl bg-teal-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-teal-700 transition">Déposer une annonce</a>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</section>
@endsection
