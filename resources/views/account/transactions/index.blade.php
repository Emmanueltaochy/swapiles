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

        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900">Mes transactions</h1>
                <p class="text-gray-500 mt-2">Suivez vos achats, ventes, expéditions et paiements.</p>
            </div>

            <a href="{{ route('account.dashboard') }}" class="inline-flex bg-white border border-gray-100 text-gray-800 font-bold px-5 py-3 rounded-2xl shadow-sm">
                Retour au compte
            </a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-8">
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-sm text-gray-500">À expédier</p>
                <p class="text-3xl font-extrabold text-orange-600 mt-2">{{ $toShipCount }}</p>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-sm text-gray-500">En livraison</p>
                <p class="text-3xl font-extrabold text-blue-600 mt-2">{{ $inTransitCount }}</p>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-sm text-gray-500">À confirmer</p>
                <p class="text-3xl font-extrabold text-purple-600 mt-2">{{ $toConfirmCount }}</p>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-sm text-gray-500">Terminées</p>
                <p class="text-3xl font-extrabold text-green-600 mt-2">{{ $completedCount }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <h2 class="text-xl font-extrabold mb-4">Mes achats</h2>

                @forelse($purchases as $transaction)
                    @include('account.transactions.partials.card', ['transaction' => $transaction, 'type' => 'purchase'])
                @empty
                    <div class="p-8 text-center text-gray-500">
                        Aucun achat pour le moment.
                    </div>
                @endforelse
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <h2 class="text-xl font-extrabold mb-4">Mes ventes</h2>

                @forelse($sales as $transaction)
                    @include('account.transactions.partials.card', ['transaction' => $transaction, 'type' => 'sale'])
                @empty
                    <div class="p-8 text-center text-gray-500">
                        Aucune vente pour le moment.
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</section>
@endsection
