@extends('layouts.app')

@section('title', 'Mes transactions — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-6xl mx-auto px-4">
        <h1 class="text-3xl font-extrabold text-gray-900">Mes transactions</h1>
        <p class="text-gray-500 mt-2">Suivez vos achats et vos ventes sécurisées.</p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
            <div class="bg-white rounded-3xl border border-gray-100 p-5">
                <h2 class="text-xl font-extrabold mb-4">Mes achats</h2>

                @forelse($purchases as $transaction)
                    @include('account.transactions.partials.card', ['transaction' => $transaction, 'type' => 'purchase'])
                @empty
                    <p class="text-gray-500">Aucun achat pour le moment.</p>
                @endforelse
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 p-5">
                <h2 class="text-xl font-extrabold mb-4">Mes ventes</h2>

                @forelse($sales as $transaction)
                    @include('account.transactions.partials.card', ['transaction' => $transaction, 'type' => 'sale'])
                @empty
                    <p class="text-gray-500">Aucune vente pour le moment.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
@endsection
