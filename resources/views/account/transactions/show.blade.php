@extends('layouts.app')

@section('title', 'Détail transaction — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-4xl mx-auto px-4">

        <a href="{{ route('account.transactions.index') }}" class="text-sm font-bold text-teal-700 hover:underline">
            ← Retour aux transactions
        </a>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 mt-5">
            <h1 class="text-3xl font-extrabold text-gray-900">Transaction sécurisée</h1>
            <p class="text-gray-500 mt-2">Suivi de votre achat/vente Swap’Îles.</p>

            <div class="mt-6 flex gap-4">
                <div class="w-24 h-24 rounded-2xl bg-gray-100 overflow-hidden shrink-0">
                    @if($transaction->listing?->images?->first())
                        <img src="{{ $transaction->listing->images->first()->url }}" class="w-full h-full object-cover">
                    @endif
                </div>

                <div>
                    <p class="font-extrabold text-gray-900">{{ $transaction->listing->title ?? 'Annonce supprimée' }}</p>
                    <p class="text-sm text-gray-500 mt-1">
                        Vendeur : {{ $transaction->seller->name ?? '-' }} · Acheteur : {{ $transaction->buyer->name ?? '-' }}
                    </p>
                    <p class="text-2xl font-extrabold text-teal-700 mt-2">
                        {{ number_format($transaction->amount, 2, ',', ' ') }} €
                    </p>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="rounded-2xl p-4 {{ in_array($transaction->status, ['paid','completed']) ? 'bg-teal-50 text-teal-800' : 'bg-gray-100 text-gray-500' }}">
                    <p class="font-extrabold">1. Paiement</p>
                    <p class="text-sm mt-1">{{ in_array($transaction->status, ['paid','completed']) ? 'Confirmé' : 'En attente' }}</p>
                </div>

                <div class="rounded-2xl p-4 {{ in_array($transaction->shipping_status, ['shipped','received']) ? 'bg-blue-50 text-blue-800' : 'bg-gray-100 text-gray-500' }}">
                    <p class="font-extrabold">2. Expédition</p>
                    <p class="text-sm mt-1">{{ in_array($transaction->shipping_status, ['shipped','received']) ? 'Expédié' : 'En attente' }}</p>
                </div>

                <div class="rounded-2xl p-4 {{ $transaction->shipping_status === 'received' ? 'bg-emerald-50 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                    <p class="font-extrabold">3. Réception</p>
                    <p class="text-sm mt-1">{{ $transaction->shipping_status === 'received' ? 'Confirmée' : 'En attente' }}</p>
                </div>

                <div class="rounded-2xl p-4 {{ $transaction->released_at ? 'bg-purple-50 text-purple-800' : 'bg-gray-100 text-gray-500' }}">
                    <p class="font-extrabold">4. Versement</p>
                    <p class="text-sm mt-1">{{ $transaction->released_at ? 'Effectué' : 'À venir' }}</p>
                </div>
            </div>

            <div class="mt-8 rounded-3xl bg-gray-50 border border-gray-100 p-5">
                <h2 class="font-extrabold text-gray-900 mb-4">Détail du paiement</h2>

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total payé</span>
                        <span class="font-extrabold">{{ number_format($transaction->amount, 2, ',', ' ') }} €</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-500">Protection acheteur</span>
                        <span class="font-extrabold text-teal-700">{{ number_format($transaction->buyer_protection_fee ?? 0, 2, ',', ' ') }} €</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-500">Montant vendeur estimé</span>
                        <span class="font-extrabold">{{ number_format($transaction->seller_amount ?? 0, 2, ',', ' ') }} €</span>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-3xl bg-gray-50 border border-gray-100 p-5">
                <h2 class="font-extrabold text-gray-900 mb-3">Mode de remise</h2>

                @if($transaction->delivery_method === 'colissimo')
                    <p class="text-sm text-gray-600">
                        📦 Livraison Colissimo
                    </p>

                    @if($transaction->shipping_address_line1)
                        <div class="mt-3 text-sm text-gray-600">
                            <p class="font-bold text-gray-900">Adresse acheteur :</p>
                            <p>{{ $transaction->buyer_full_name }}</p>
                            <p>{{ $transaction->shipping_address_line1 }}</p>
                            @if($transaction->shipping_address_line2)
                                <p>{{ $transaction->shipping_address_line2 }}</p>
                            @endif
                            <p>{{ $transaction->shipping_postal_code }} {{ $transaction->shipping_city }}</p>
                            <p>{{ $transaction->shipping_country }}</p>
                            @if($transaction->buyer_phone)
                                <p>Tél : {{ $transaction->buyer_phone }}</p>
                            @endif
                        </div>
                    @endif
                @else
                    <p class="text-sm text-gray-600">
                        🤝 Remise en main propre
                    </p>
                    <p class="mt-2 text-sm font-bold text-gray-900">
                        RDV proposé : {{ $transaction->hand_delivery_location ?: $transaction->listing?->hand_delivery_location ?: $transaction->listing?->location_address ?: 'À convenir avec le vendeur' }}
                    </p>
                @endif
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                @if(auth()->id() === $transaction->seller_id && $transaction->status === 'paid' && $transaction->delivery_method === 'colissimo')
                    @if(!$transaction->colissimo_label_path)
                        <form method="POST" action="{{ route('account.colissimo.generate', $transaction) }}">
                            @csrf
                            <button class="bg-blue-700 hover:bg-blue-800 text-white font-extrabold rounded-2xl px-5 py-3">
                                Générer le bordereau Colissimo
                            </button>
                        </form>
                    @else
                        <a href="{{ route('account.colissimo.download', $transaction) }}"
                           class="bg-gray-900 hover:bg-black text-white font-extrabold rounded-2xl px-5 py-3">
                            Voir / imprimer le bordereau
                        </a>

                        @if($transaction->shipping_status === 'pending')
                            <form method="POST" action="{{ route('transactions.shipped', $transaction) }}">
                                @csrf
                                @method('PATCH')
                                <button class="bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-5 py-3">
                                    Marquer comme expédié
                                </button>
                            </form>
                        @endif
                    @endif
                @endif

                @if(auth()->id() === $transaction->seller_id && $transaction->status === 'paid' && $transaction->delivery_method === 'hand_delivery' && $transaction->shipping_status === 'pending')
                    <form method="POST" action="{{ route('transactions.shipped', $transaction) }}">
                        @csrf
                        @method('PATCH')
                        <button class="bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-5 py-3">
                            Remise effectuée
                        </button>
                    </form>
                @endif

                @if(auth()->id() === $transaction->buyer_id && $transaction->status === 'paid' && in_array($transaction->shipping_status, ['pending','shipped']))
                    <form method="POST" action="{{ route('transactions.received', $transaction) }}">
                        @csrf
                        @method('PATCH')
                        <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-2xl px-5 py-3">
                            Confirmer réception
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
