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
                            @if($message->hasAttachment())
                                <div class="mb-2 overflow-hidden rounded-xl">
                                    @if($message->isVideoAttachment())
                                        <video controls preload="metadata" class="max-h-80 w-full rounded-xl bg-black">
                                            <source src="{{ $message->attachmentUrl() }}" type="{{ $message->attachment_mime }}">
                                        </video>
                                    @else
                                        <a href="{{ $message->attachmentUrl() }}" target="_blank" rel="noopener">
                                            <img src="{{ $message->attachmentUrl() }}" alt="Pièce jointe" loading="lazy" class="max-h-80 w-full rounded-xl object-cover">
                                        </a>
                                    @endif
                                </div>
                            @endif
                            @if(filled($message->body))
                                <p class="whitespace-pre-line">{{ $message->body }}</p>
                            @endif

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
                                    <img src="{{ $exPhoto }}" class="h-full w-full object-cover js-zoomable cursor-zoom-in" alt="Photo de l'échange">
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

            @php
                $flaggedPayment = isset($messages) && $messages->contains(fn ($m) => ($m->flag_kind ?? null) === 'payment_forced');
            @endphp
            @if($flaggedPayment)
                <div class="border-t border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p class="font-bold">⚠️ Paiement hors plateforme détecté dans cette conversation</p>
                    <p class="mt-1">Ne validez jamais un lien de paiement reçu par SMS. Privilégiez le
                        <span class="font-semibold">paiement sécurisé Swap'Îles</span> (fonds versés au vendeur après confirmation de réception)
                        ou les <span class="font-semibold">espèces en main propre</span>.</p>
                </div>
            @endif

            @if(session('moderation_payment_warning'))
                <div class="border-t border-amber-300 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                    <p class="text-base font-bold">⚠️ {{ \App\Support\MessageModeration::PAYMENT_WARNING_TITLE }}</p>
                    @foreach(\App\Support\MessageModeration::paymentWarningLines() as $line)
                        <p class="mt-1">{{ $line }}</p>
                    @endforeach
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" id="mod-edit"
                                class="rounded-xl border border-amber-400 bg-white px-4 py-2 font-semibold text-amber-800 hover:bg-amber-100">Modifier mon message</button>
                        <button type="button" id="mod-send-anyway"
                                class="rounded-xl bg-amber-600 px-4 py-2 font-semibold text-white hover:bg-amber-700">Envoyer quand même</button>
                    </div>
                </div>
            @endif

            <form method="POST" id="compose-form"
                  action="{{ $hasListing ? route('account.messages.store', ['listing' => $listing, 'user' => $user]) : route('account.messages.store.general', $user) }}"
                  enctype="multipart/form-data"
                  class="border-t border-gray-100 bg-white p-4">
                @csrf
                <input type="hidden" name="moderation_confirm" id="moderation_confirm" value="">
                <div class="flex items-end gap-2">
                    <label for="attachment" title="Ajouter une photo ou une vidéo" aria-label="Ajouter une photo ou une vidéo"
                           class="grid h-11 w-11 shrink-0 cursor-pointer place-items-center rounded-xl border border-gray-200 text-xl transition hover:bg-gray-50">📎</label>
                    <input id="attachment" name="attachment" type="file" accept="image/*,video/*" class="hidden">

                    <label for="body" class="sr-only">Message</label>
                    <textarea id="body" name="body" rows="2" placeholder="Écrire un message…"
                              class="flex-1 resize-none rounded-xl border border-gray-200 bg-white px-4 py-3 outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-100">{{ old('body') }}</textarea>
                    <button class="shrink-0 rounded-xl bg-teal-600 px-5 py-3 font-semibold text-white transition hover:bg-teal-700">Envoyer</button>
                </div>

                <div id="attachment-preview" class="mt-2 hidden items-center gap-2 text-sm text-gray-600">
                    <span aria-hidden="true">📎</span>
                    <span id="attachment-name" class="max-w-[60%] truncate"></span>
                    <button type="button" id="attachment-remove" class="font-semibold text-red-600 hover:text-red-700">Retirer</button>
                </div>

                <p class="mt-2 text-xs text-gray-400">Photo ou vidéo, 50 Mo max.</p>
                @error('body')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @error('attachment')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </form>

            <script>
                (function () {
                    var input = document.getElementById('attachment');
                    var preview = document.getElementById('attachment-preview');
                    var nameEl = document.getElementById('attachment-name');
                    var removeBtn = document.getElementById('attachment-remove');
                    if (!input) return;
                    input.addEventListener('change', function () {
                        if (input.files && input.files.length) {
                            nameEl.textContent = input.files[0].name;
                            preview.classList.remove('hidden');
                            preview.classList.add('flex');
                        }
                    });
                    removeBtn && removeBtn.addEventListener('click', function () {
                        input.value = '';
                        preview.classList.add('hidden');
                        preview.classList.remove('flex');
                    });

                    // Modération : « Modifier » recentre le champ, « Envoyer quand même »
                    // renvoie le formulaire avec la confirmation.
                    var modEdit = document.getElementById('mod-edit');
                    var modSend = document.getElementById('mod-send-anyway');
                    var body = document.getElementById('body');
                    modEdit && modEdit.addEventListener('click', function () {
                        body && body.focus();
                    });
                    modSend && modSend.addEventListener('click', function () {
                        var confirmField = document.getElementById('moderation_confirm');
                        var form = document.getElementById('compose-form');
                        if (confirmField && form) {
                            confirmField.value = '1';
                            form.submit();
                        }
                    });
                })();
            </script>

        </div>
    </div>
</section>

{{-- Lightbox : agrandir les photos au clic --}}
<div id="msg-lightbox" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/80 p-4" role="dialog" aria-modal="true">
    <button type="button" id="msg-lightbox-close" class="absolute right-4 top-4 grid h-11 w-11 place-items-center rounded-full bg-white/15 text-2xl text-white hover:bg-white/25" aria-label="Fermer">✕</button>
    <img id="msg-lightbox-img" src="" alt="" class="max-h-[90vh] max-w-[95vw] rounded-2xl object-contain shadow-2xl">
</div>

<script>
(function () {
    const box = document.getElementById('msg-lightbox');
    const img = document.getElementById('msg-lightbox-img');
    const closeBtn = document.getElementById('msg-lightbox-close');
    if (!box || !img) return;

    function open(src) {
        img.src = src;
        box.classList.remove('hidden');
        box.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
    function close() {
        box.classList.add('hidden');
        box.classList.remove('flex');
        img.src = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (e) {
        const zoomable = e.target.closest('.js-zoomable');
        if (zoomable && zoomable.tagName === 'IMG') {
            e.preventDefault();
            open(zoomable.currentSrc || zoomable.src);
        }
    });

    box.addEventListener('click', function (e) {
        if (e.target === box || e.target === closeBtn || closeBtn.contains(e.target)) {
            close();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
})();
</script>
@endsection
