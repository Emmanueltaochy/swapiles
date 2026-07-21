@extends('layouts.app')

@section('title', 'Conversation — Swap\'Îles')

@section('content')

@php
    $transactionChatBadge = \App\Models\Transaction::where('listing_id', $listing->id)
        ->where(function ($q) {
            $q->where('buyer_id', auth()->id())
              ->orWhere('seller_id', auth()->id());
        })
        ->latest()
        ->first();

    $transactionStatusLabels = [
        'pending' => 'Paiement en attente',
        'paid' => 'Paiement confirmé',
        'completed' => 'Transaction terminée',
        'cancelled' => 'Transaction annulée',
    ];

    $shippingStatusLabels = [
        'pending' => 'En attente d’expédition',
        'shipped' => 'Article expédié',
        'received' => 'Article reçu',
    ];
@endphp

@if($transactionChatBadge)
    <div class="mb-4 rounded-3xl border border-teal-100 bg-teal-50 p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="font-extrabold text-teal-900">
                    Transaction sécurisée
                </p>

                <p class="text-sm text-teal-800 mt-1">
                    {{ $transactionStatusLabels[$transactionChatBadge->status] ?? $transactionChatBadge->status }}
                    ·
                    {{ $shippingStatusLabels[$transactionChatBadge->shipping_status] ?? $transactionChatBadge->shipping_status }}
                </p>

                <p class="text-sm font-extrabold text-teal-900 mt-2">
                    {{ number_format($transactionChatBadge->amount, 2, ',', ' ') }} €
                </p>
            </div>

            <a href="{{ route('account.transactions.show', $transactionChatBadge) }}"
               class="shrink-0 bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-4 py-2 text-sm">
                Voir
            </a>
        </div>
    </div>
@endif


@php
    $acceptedOfferForBuyer = \App\Models\ListingOffer::where('listing_id', $listing->id)
        ->where('buyer_id', auth()->id())
        ->where('status', 'accepted')
        ->latest()
        ->first();
@endphp

@if($acceptedOfferForBuyer)
    <div class="mb-4 rounded-3xl border border-emerald-100 bg-emerald-50 p-4">
        <p class="font-extrabold text-emerald-900">
            ✅ Offre acceptée : {{ number_format($acceptedOfferForBuyer->amount, 0, ',', ' ') }} €
        </p>
        <p class="text-sm text-emerald-800 mt-1">
            Vous pouvez maintenant finaliser l’achat sécurisé au prix accepté.
        </p>

        <a href="{{ route('checkout.show', ['listing' => $listing, 'offer' => $acceptedOfferForBuyer->id]) }}"
           class="inline-flex mt-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-2xl px-4 py-2 text-sm">
            Finaliser l’achat au prix accepté
        </a>
    </div>
@endif


@if($acceptedOfferForBuyer)
    <div class="mb-4 rounded-3xl border border-emerald-100 bg-emerald-50 p-4">
        <p class="font-extrabold text-emerald-900">
            Offre acceptée : {{ number_format($acceptedOfferForBuyer->amount, 0, ',', ' ') }} €
        </p>
        <p class="text-sm text-emerald-800 mt-1">
            Vous pouvez finaliser l’achat au prix accepté.
        </p>

        <a href="{{ route('checkout.show', ['listing' => $listing, 'offer' => $acceptedOfferForBuyer->id]) }}"
           class="inline-flex mt-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-2xl px-4 py-2 text-sm">
            Acheter à {{ number_format($acceptedOfferForBuyer->amount, 0, ',', ' ') }} €
        </a>
    </div>
@endif

<section class="bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <div class="mb-4">
            <a href="{{ route('account.messages.index') }}" class="text-sm font-bold text-teal-700 hover:text-teal-900">
                ← Retour aux messages
            </a>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">

            <div class="p-4 border-b border-gray-100 flex gap-4 items-center">
                <a href="{{ route('listings.show', $listing) }}" class="w-16 h-16 rounded-2xl bg-gray-100 overflow-hidden shrink-0">
                    @if($listing->images->first())
                        <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-300 text-2xl">📦</div>
                    @endif
                </a>

                <div class="flex-1 min-w-0">
                    <p class="font-extrabold text-gray-900 truncate">{{ $listing->title }}</p>
                    <p class="text-sm text-gray-500">
                        Conversation avec <span class="font-bold text-gray-700">{{ $user->name }}</span>
                    </p>
                </div>
            </div>

            <div class="p-4 sm:p-6 space-y-4 bg-gray-50 min-h-[420px]">
                @forelse($messages as $message)
                    @php $mine = $message->sender_id === auth()->id(); @endphp

                    <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[80%] rounded-3xl px-4 py-3 text-sm
                            {{ $mine ? 'bg-teal-700 text-white rounded-br-md' : 'bg-white text-gray-800 border border-gray-100 rounded-bl-md' }}">
                            <p class="whitespace-pre-line">{{ $message->body }}</p>
                            <p class="mt-2 text-[11px] {{ $mine ? 'text-white/70' : 'text-gray-400' }}">
                                {{ $message->created_at->format('d/m/Y H:i') }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-16">
                        <div class="text-5xl mb-3">💬</div>
                        <h2 class="text-xl font-bold text-gray-900">Démarrer la conversation</h2>
                        <p class="text-gray-500 mt-2">Envoyez un premier message concernant cette annonce.</p>
                    </div>
                @endforelse
            </div>

            <form method="POST" action="{{ route('account.messages.store', ['listing' => $listing, 'user' => $user]) }}" class="p-4 bg-white border-t border-gray-100">
                @csrf

                <div class="flex gap-3">
                    <textarea
                        name="body"
                        rows="2"
                        required
                        placeholder="Écrire un message..."
                        class="flex-1 rounded-2xl bg-gray-100 border-0 px-4 py-3 focus:ring-2 focus:ring-teal-600 resize-none"
                    ></textarea>

                    <button class="bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-5 py-3 transition">
                        Envoyer
                    </button>
                </div>

                @error('body')
                    <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
                @enderror
            </form>

        </div>

    </div>
</section>
@endsection
