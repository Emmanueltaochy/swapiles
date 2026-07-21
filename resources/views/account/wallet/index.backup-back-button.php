@extends('layouts.app')

@section('title', 'Mon wallet — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        <h1 class="text-3xl font-extrabold text-gray-900">Mon wallet</h1>
        <p class="text-gray-500 mt-2">Suivez vos ventes, vos virements et votre argent disponible.</p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-8">
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-sm text-gray-500">En attente</p>
                <p class="text-3xl font-extrabold text-gray-900 mt-2">{{ number_format($pendingAmount, 0, ',', ' ') }} €</p>
                <p class="text-xs text-gray-400 mt-1">Paiement reçu, réception non confirmée.</p>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-sm text-gray-500">Virement en cours</p>
                <p class="text-3xl font-extrabold text-yellow-700 mt-2">{{ number_format($processingAmount, 0, ',', ' ') }} €</p>
                <p class="text-xs text-gray-400 mt-1">Délai estimé : 1 à 3 jours ouvrés.</p>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5">
                <p class="text-sm text-gray-500">Déjà versé</p>
                <p class="text-3xl font-extrabold text-green-700 mt-2">{{ number_format($paidAmount, 0, ',', ' ') }} €</p>
                <p class="text-xs text-gray-400 mt-1">Argent envoyé sur votre compte bancaire.</p>
            </div>
        </div>

        @if(!$user->stripe_account_id || !$user->stripe_payouts_enabled)
            <div class="bg-yellow-50 border border-yellow-200 rounded-3xl p-5 mt-8">
                <h2 class="text-xl font-extrabold text-yellow-900">Recevoir mes paiements</h2>
                <p class="text-sm text-yellow-800 mt-1">
                    Ajoutez votre IBAN et finalisez la vérification pour recevoir automatiquement vos ventes.
                </p>

                <a href="{{ route('stripe.connect.onboarding') }}" class="inline-flex mt-4 bg-yellow-500 hover:bg-yellow-600 text-white font-extrabold px-6 py-3 rounded-2xl transition">
                    {{ $user->stripe_account_id ? 'Terminer la configuration' : 'Ajouter mon IBAN' }}
                </a>
            </div>
        @endif

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden mt-8">
            <div class="p-5 border-b border-gray-100">
                <h2 class="text-xl font-extrabold text-gray-900">Historique des ventes</h2>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse($sales as $sale)
                    <div class="p-4 flex gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-gray-100 overflow-hidden shrink-0">
                            @if($sale->listing && $sale->listing->images->first())
                                <img src="{{ $sale->listing->images->first()->url }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-300 text-2xl">📦</div>
                            @endif
                        </div>

                        <div class="flex-1">
                            <p class="font-bold text-gray-900">{{ $sale->listing->title ?? 'Annonce supprimée' }}</p>
                            <p class="text-sm text-gray-500">Acheteur : {{ $sale->buyer->name ?? 'Utilisateur' }}</p>
                            <p class="text-sm font-extrabold text-gray-900 mt-1">
                                {{ number_format(max(0, $sale->amount - $sale->commission), 0, ',', ' ') }} € net vendeur
                            </p>
                        </div>

                        <div class="text-right">
                            @if($sale->wallet_status === 'paid')
                                <span class="text-xs font-bold px-3 py-1 rounded-full bg-green-100 text-green-800">Virement envoyé</span>
                            @elseif($sale->wallet_status === 'processing')
                                <span class="text-xs font-bold px-3 py-1 rounded-full bg-yellow-100 text-yellow-800">Virement en cours</span>
                            @elseif($sale->status === 'paid')
                                <span class="text-xs font-bold px-3 py-1 rounded-full bg-gray-100 text-gray-700">En attente réception</span>
                            @else
                                <span class="text-xs font-bold px-3 py-1 rounded-full bg-gray-100 text-gray-700">{{ $sale->status }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-10 text-center text-gray-500">
                        Aucune vente pour le moment.
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</section>
@endsection
