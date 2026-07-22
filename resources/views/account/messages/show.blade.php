@extends('layouts.app')

@section('title', 'Conversation — Swap\'Îles')

@section('content')

@php
    $hasListing = isset($listing) && $listing;

    $transactionChatBadge = null;

    if ($hasListing) {
        $transactionChatBadge = \App\Models\Transaction::where('listing_id', $listing->id)
            ->where(function ($q) {
                $q->where('buyer_id', auth()->id())
                  ->orWhere('seller_id', auth()->id());
            })
            ->latest()
            ->first();
    }

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

<section class="bg-gray-50 min-h-screen">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-6">

        <div class="mb-4">
            <a href="{{ route('account.messages.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">← Retour aux messages</a>
        </div>

        @if($transactionChatBadge)
            <div class="mb-4 rounded-2xl border border-teal-100 bg-teal-50 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-teal-900">🛡️ Transaction sécurisée</p>
                        <p class="mt-1 text-sm text-teal-800">
                            {{ $transactionStatusLabels[$transactionChatBadge->status] ?? $transactionChatBadge->status }}
                            · {{ $shippingStatusLabels[$transactionChatBadge->shipping_status] ?? $transactionChatBadge->shipping_status }}
                        </p>
                        <p class="mt-2 text-sm font-bold text-teal-900">{{ number_format($transactionChatBadge->amount, 2, ',', ' ') }} €</p>
                    </div>
                    <a href="{{ route('account.transactions.show', $transactionChatBadge) }}"
                       class="shrink-0 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">Voir</a>
                </div>
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">

            <div class="flex items-center gap-4 border-b border-gray-100 p-4">
                @if($hasListing)
                    <a href="{{ route('listings.show', $listing) }}" class="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-gray-100">
                        @if($listing->images->first())
                            <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" class="h-full w-full object-cover">
                        @else
                            <div class="grid h-full w-full place-items-center text-2xl text-gray-300" aria-hidden="true">📦</div>
                        @endif
                    </a>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-gray-900">{{ $listing->title }}</p>
                        <p class="text-sm text-gray-500">Avec <span class="font-medium text-gray-700">{{ $user->name }}</span></p>
                    </div>
                @else
                    <div class="grid h-14 w-14 shrink-0 place-items-center rounded-xl bg-teal-50 text-2xl" aria-hidden="true">💬</div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-gray-900">{{ $user->name }}</p>
                        <p class="text-sm text-gray-500">Conversation directe</p>
                    </div>
                @endif
            </div>

            <div class="min-h-[420px] space-y-4 bg-gray-50 p-4 sm:p-6">
                @forelse($messages as $message)
                    @php $mine = $message->sender_id === auth()->id(); @endphp

                    <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[80%] rounded-2xl px-4 py-3 text-sm {{ $mine ? 'rounded-br-sm bg-teal-600 text-white' : 'rounded-bl-sm border border-gray-100 bg-white text-gray-800' }}">
                            <p class="whitespace-pre-line">{{ $message->body }}</p>

                            @if($hasListing)
                                @php
                                    $inlineOffer = null;

                                    if (!$mine && isset($pendingOffers) && str_contains($message->body, 'Nouvelle offre')) {
                                        foreach ($pendingOffers as $offer) {
                                            if (str_contains($message->body, (string) $offer->amount)) {
                                                $inlineOffer = $offer;
                                                break;
                                            }
                                        }
                                    }

                                    $acceptedInlineOffer = null;

                                    if (!$mine && str_contains($message->body, 'offre de') && str_contains($message->body, 'acceptée')) {
                                        foreach (\App\Models\ListingOffer::where('listing_id', $listing->id)
                                            ->where('buyer_id', auth()->id())
                                            ->where('status', 'accepted')
                                            ->latest()
                                            ->get() as $acceptedOffer) {
                                            if (str_contains($message->body, (string) $acceptedOffer->amount)) {
                                                $acceptedInlineOffer = $acceptedOffer;
                                                break;
                                            }
                                        }
                                    }
                                @endphp

                                @if($acceptedInlineOffer)
                                    <div class="mt-3 rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-emerald-900">
                                        <p class="text-sm font-semibold">✅ Offre acceptée</p>
                                        <a href="{{ route('checkout.show', ['listing' => $listing, 'offer' => $acceptedInlineOffer->id]) }}"
                                           class="mt-3 inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                                            Acheter à {{ number_format($acceptedInlineOffer->amount, 0, ',', ' ') }} €
                                        </a>
                                    </div>
                                @endif

                                @if($inlineOffer)
                                    <div class="mt-3 rounded-xl border border-teal-100 bg-white p-3 text-gray-900 shadow-sm">
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-teal-700">Répondre à cette offre</p>

                                        <div class="flex flex-wrap gap-2">
                                            <form method="POST" action="{{ route('offers.accept', $inlineOffer) }}">
                                                @csrf
                                                <button class="rounded-lg bg-teal-600 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-700">Accepter</button>
                                            </form>
                                            <form method="POST" action="{{ route('offers.refuse', $inlineOffer) }}">
                                                @csrf
                                                <button class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Refuser</button>
                                            </form>
                                        </div>

                                        <form method="POST" action="{{ route('offers.counter', ['listing' => $listing, 'user' => $user]) }}" class="mt-2 flex gap-2">
                                            @csrf
                                            <input type="number" name="amount" min="1" required placeholder="Autre prix"
                                                   class="w-28 rounded-lg border border-gray-200 px-3 py-2 text-xs text-gray-900 outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100">
                                            <button class="rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white hover:bg-black">Contre-offre</button>
                                        </form>
                                    </div>
                                @endif
                            @endif

                            <p class="mt-2 text-[11px] {{ $mine ? 'text-white/70' : 'text-gray-400' }}">{{ $message->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="py-16 text-center">
                        <div class="text-5xl" aria-hidden="true">💬</div>
                        <h2 class="mt-3 text-lg font-bold text-gray-900">Démarrer la conversation</h2>
                        <p class="mt-1 text-gray-500">{{ $hasListing ? 'Envoie un premier message concernant cette annonce.' : 'Envoie un premier message à ce membre.' }}</p>
                    </div>
                @endforelse

                @foreach($exchangeProposals as $proposal)
                    @php
                        $exPhoto = $proposal->photoUrl();
                        $exStatus = [
                            'pending' => ['⏳ En attente', 'bg-amber-50 text-amber-800 border-amber-100'],
                            'accepted' => ['✅ Acceptée', 'bg-emerald-50 text-emerald-800 border-emerald-100'],
                            'refused' => ['❌ Refusée', 'bg-red-50 text-red-700 border-red-100'],
                        ];
                        [$exLabel, $exClass] = $exStatus[$proposal->status] ?? ['—', 'bg-gray-50 text-gray-600 border-gray-100'];
                    @endphp
                    <div class="rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">🔄 Proposition d'échange</p>
                            <span class="rounded-full border px-2.5 py-0.5 text-xs font-bold {{ $exClass }}">{{ $exLabel }}</span>
                        </div>
                        <div class="flex gap-3">
                            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-gray-100">
                                @if($exPhoto)
                                    <img src="{{ $exPhoto }}" class="h-full w-full object-cover" alt="">
                                @else
                                    <div class="grid h-full w-full place-items-center text-2xl text-gray-300">🔄</div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-gray-900">{{ $proposal->displayTitle() }}</p>
                                @if($proposal->offered_condition)
                                    <p class="text-sm text-gray-500">État : {{ $proposal->offered_condition }}</p>
                                @endif
                                @if($proposal->offered_description)
                                    <p class="mt-1 text-sm text-gray-600">{{ $proposal->offered_description }}</p>
                                @endif
                                @if($proposal->message)
                                    <p class="mt-1 text-sm italic text-gray-500">« {{ $proposal->message }} »</p>
                                @endif
                                <p class="mt-1 text-xs text-gray-400">Proposé par {{ $proposal->proposer->name ?? 'un membre' }}</p>
                            </div>
                        </div>

                        @if($proposal->status === 'pending' && $proposal->seller_id === auth()->id())
                            <div class="mt-4 flex gap-2">
                                <form method="POST" action="{{ route('exchange.accept', $proposal) }}">
                                    @csrf
                                    <button class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">Accepter l'échange</button>
                                </form>
                                <form method="POST" action="{{ route('exchange.refuse', $proposal) }}">
                                    @csrf
                                    <button class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Refuser</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <form method="POST"
                  action="{{ $hasListing ? route('account.messages.store', ['listing' => $listing, 'user' => $user]) : route('account.messages.store.general', $user) }}"
                  class="border-t border-gray-100 bg-white p-4">
                @csrf
                <div class="flex gap-3">
                    <label for="body" class="sr-only">Message</label>
                    <textarea id="body" name="body" rows="2" required placeholder="Écrire un message…"
                              class="flex-1 resize-none rounded-xl border border-gray-200 bg-white px-4 py-3 outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100"></textarea>
                    <button class="shrink-0 rounded-xl bg-teal-600 px-5 font-semibold text-white transition hover:bg-teal-700">Envoyer</button>
                </div>
                @error('body')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </form>

        </div>
    </div>
</section>
@endsection
