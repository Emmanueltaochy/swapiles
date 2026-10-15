@extends('layouts.app')

@section('title', 'Conversation — Swap\'Îles')

{{-- Page à scripts lourds (paiement, photos, messagerie) : toujours ouverte
     par un rechargement complet, même si l'on y arrive sans recharger
     (redirection). Voir resources/js/app.js. --}}
@push('head')
    <meta name="turbo-visit-control" content="reload">
@endpush

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

    $iBlockedThem = auth()->user()->hasBlocked($user);
    $theyBlockedMe = auth()->user()->isBlockedBy($user);
    $conversationBlocked = $iBlockedThem || $theyBlockedMe;
    $flaggedPayment = isset($messages) && $messages->contains(fn ($m) => ($m->flag_kind ?? null) === 'payment_forced');
    $initiale = mb_strtoupper(mb_substr($user->name ?? '?', 0, 1));
    $jourLisible = function (\Illuminate\Support\Carbon $d) {
        return $d->isToday() ? 'Aujourd’hui' : ($d->isYesterday() ? 'Hier' : ucfirst($d->locale('fr')->translatedFormat($d->isCurrentYear() ? 'l j F' : 'j F Y')));
    };
    $extrait = fn ($m) => $m ? (filled($m->body) ? \Illuminate\Support\Str::limit($m->body, 90) : ($m->hasAttachment() ? ($m->isVideoAttachment() ? '🎥 Vidéo' : '📷 Photo') : '')) : '';
@endphp

{{-- Conversation plein écran, comme Instagram : en-tête en haut, messages au
     milieu (seuls à défiler), saisie toujours en bas. --}}
<section data-conversation class="fixed inset-x-0 top-0 z-[60] flex flex-col bg-white" style="height:100dvh">

    {{-- En-tête --}}
    <header class="flex shrink-0 items-center gap-3 border-b border-gray-100 bg-white px-2 py-2" style="padding-top:max(8px, env(safe-area-inset-top))">
        <a href="{{ route('account.messages.index') }}" class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-2xl text-gray-800 hover:bg-gray-100" aria-label="Retour aux messages">‹</a>

        <a href="{{ $hasListing ? route('listings.show', $listing) : route('profiles.show', $user) }}" class="flex min-w-0 flex-1 items-center gap-3">
            @if($hasListing && $listing->images->first())
                <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" class="h-10 w-10 shrink-0 rounded-full object-cover ring-1 ring-gray-100">
            @else
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-teal-600 font-bold text-white">{{ $initiale }}</span>
            @endif
            <span class="min-w-0 leading-tight">
                <span class="block truncate font-bold text-gray-900">{{ $user->name }}</span>
                <span class="block truncate text-xs text-gray-500">{{ $hasListing ? $listing->title : 'Conversation directe' }}</span>
            </span>
        </a>

        <details class="account-menu relative shrink-0">
            <summary class="grid h-10 w-10 cursor-pointer list-none place-items-center rounded-full text-xl font-bold text-gray-700 hover:bg-gray-100 [&::-webkit-details-marker]:hidden" aria-label="Plus d’options">⋯</summary>
            <div class="absolute right-0 top-11 z-20 w-56 space-y-1 rounded-2xl border border-gray-100 bg-white p-2 shadow-xl">
                @if($hasListing)
                    <a href="{{ route('listings.show', $listing) }}" class="block rounded-xl px-3 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">📦 Voir l’annonce</a>
                @endif
                <a href="{{ route('profiles.show', $user) }}" class="block rounded-xl px-3 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">👤 Voir le profil</a>
                <div class="px-1 py-1">
                    @include('partials.report', ['action' => route('reports.user', $user), 'label' => 'ce membre', 'align' => 'right'])
                </div>
                <form method="POST" action="{{ route('users.block.toggle', $user) }}" class="px-1 pb-1">
                    @csrf
                    <button class="w-full rounded-xl border {{ $iBlockedThem ? 'border-teal-200 bg-teal-50 text-teal-700' : 'border-gray-200 text-gray-600' }} px-4 py-2.5 text-left text-sm font-semibold">
                        {{ $iBlockedThem ? '✓ Débloquer ce membre' : '🚫 Bloquer ce membre' }}
                    </button>
                </form>
            </div>
        </details>
    </header>

    @if($transactionChatBadge)
        <a href="{{ route('account.transactions.show', $transactionChatBadge) }}" class="flex shrink-0 items-center gap-2 border-b border-teal-100 bg-teal-50 px-4 py-2 text-xs text-teal-900">
            <span aria-hidden="true">🛡️</span>
            <span class="min-w-0 flex-1 truncate"><strong>{{ $transactionStatusLabels[$transactionChatBadge->status] ?? $transactionChatBadge->status }}</strong> · {{ $shippingStatusLabels[$transactionChatBadge->shipping_status] ?? $transactionChatBadge->shipping_status }} · {{ number_format($transactionChatBadge->amount, 2, ',', ' ') }} €</span>
            <span class="shrink-0 font-bold">Voir ›</span>
        </a>
    @endif

    {{-- Messages : la seule zone qui défile --}}
    <div data-fil class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-white px-3 py-4">
        @php $jourPrecedent = null; @endphp
        @forelse($messages as $message)
            @php
                $mine = $message->sender_id === auth()->id();
                $jour = $message->created_at->toDateString();
            @endphp

            @if($jour !== $jourPrecedent)
                <p class="my-3 text-center text-xs font-semibold text-gray-400">{{ $jourLisible($message->created_at) }}</p>
                @php $jourPrecedent = $jour; @endphp
            @endif

            <div class="group relative mb-1.5 flex items-end {{ $mine ? 'justify-end' : 'justify-start' }}"
                 data-message-id="{{ $message->id }}"
                 data-message-auteur="{{ $mine ? 'ton message' : $user->name }}"
                 data-message-extrait="{{ $extrait($message) }}">
                {{-- Flèche « répondre » qui apparaît en glissant --}}
                <span data-fleche-reponse class="pointer-events-none absolute left-0 top-1/2 -translate-y-1/2 text-lg text-gray-400 opacity-0" aria-hidden="true">↩</span>

                @if($mine)
                    <button type="button" data-repondre class="mr-1 hidden h-8 w-8 shrink-0 place-items-center rounded-full text-gray-400 hover:bg-gray-100 md:group-hover:grid" aria-label="Répondre">↩</button>
                @endif

                <div data-bulle class="max-w-[78%] transition-transform will-change-transform">
                    @if($message->replyTo)
                        <button type="button" data-aller-a="{{ $message->replyTo->id }}"
                                class="mb-0.5 block w-full rounded-2xl {{ $mine ? 'border-r-4 border-teal-300 bg-teal-50 text-right' : 'border-l-4 border-gray-300 bg-gray-50 text-left' }} px-3 py-1.5 text-xs text-gray-600">
                            <span class="block font-semibold text-gray-700">{{ $message->replyTo->sender_id === auth()->id() ? 'Toi' : ($message->replyTo->sender->name ?? $user->name) }}</span>
                            <span class="line-clamp-2">{{ $extrait($message->replyTo) }}</span>
                        </button>
                    @endif

                    <div class="rounded-3xl px-4 py-2.5 text-[15px] leading-snug {{ $mine ? 'rounded-br-md bg-teal-600 text-white' : 'rounded-bl-md bg-gray-100 text-gray-900' }}">
                        @if($message->hasAttachment())
                            <div class="-mx-2 mb-2 overflow-hidden rounded-2xl">
                                @if($message->isVideoAttachment())
                                    <video controls preload="metadata" class="max-h-80 w-full rounded-2xl bg-black">
                                        <source src="{{ $message->attachmentUrl() }}" type="{{ $message->attachment_mime }}">
                                    </video>
                                @else
                                    <img src="{{ $message->attachmentUrl() }}" alt="Pièce jointe" loading="lazy" class="js-zoomable max-h-80 w-full cursor-zoom-in rounded-2xl object-cover">
                                @endif
                            </div>
                        @endif
                        @if(filled($message->body))
                            <p class="whitespace-pre-line break-words">{{ $message->body }}</p>
                        @endif

                        @if($hasListing)
                            @php
                                $inlineOffer = null;

                                if (!$mine && isset($pendingOffers) && str_contains((string) $message->body, 'Nouvelle offre')) {
                                    foreach ($pendingOffers as $offer) {
                                        if (str_contains($message->body, (string) $offer->amount)) {
                                            $inlineOffer = $offer;
                                            break;
                                        }
                                    }
                                }

                                $acceptedInlineOffer = null;

                                if (!$mine && str_contains((string) $message->body, 'offre de') && str_contains((string) $message->body, 'acceptée')) {
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
                                <div class="mt-3 rounded-2xl border border-emerald-100 bg-emerald-50 p-3 text-emerald-900">
                                    <p class="text-sm font-semibold">✅ Offre acceptée</p>
                                    <a href="{{ route('checkout.show', ['listing' => $listing, 'offer' => $acceptedInlineOffer->id]) }}"
                                       class="mt-3 inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                                        Acheter à {{ number_format($acceptedInlineOffer->amount, 0, ',', ' ') }} €
                                    </a>
                                </div>
                            @endif

                            @if($inlineOffer)
                                <div class="mt-3 rounded-2xl border border-teal-100 bg-white p-3 text-gray-900 shadow-sm">
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

                        <p class="mt-1 text-right text-[10px] {{ $mine ? 'text-white/70' : 'text-gray-400' }}">{{ $message->created_at->format('H:i') }}</p>
                    </div>
                </div>

                @unless($mine)
                    <button type="button" data-repondre class="ml-1 hidden h-8 w-8 shrink-0 place-items-center rounded-full text-gray-400 hover:bg-gray-100 md:group-hover:grid" aria-label="Répondre">↩</button>
                @endunless
            </div>
        @empty
            <div class="flex h-full flex-col items-center justify-center py-16 text-center">
                <span class="grid h-20 w-20 place-items-center rounded-full bg-teal-600 text-3xl font-bold text-white">{{ $initiale }}</span>
                <h2 class="mt-3 text-lg font-bold text-gray-900">{{ $user->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $hasListing ? 'Envoie un premier message concernant cette annonce.' : 'Envoie un premier message à ce membre.' }}</p>
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
            <div class="my-3 rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">🔄 Proposition d'échange</p>
                    <span class="rounded-full border px-2.5 py-0.5 text-xs font-bold {{ $exClass }}">{{ $exLabel }}</span>
                </div>
                <div class="flex gap-3">
                    <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-gray-100">
                        @if($exPhoto)
                            <img src="{{ $exPhoto }}" class="js-zoomable h-full w-full cursor-zoom-in object-cover" alt="Photo de l'échange">
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

    {{-- Alertes au-dessus de la saisie --}}
    @if(session('error'))
        <div class="shrink-0 border-t border-red-100 bg-red-50 px-4 py-2.5 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    @if($flaggedPayment)
        <div class="shrink-0 border-t border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-900">
            <p class="font-bold">⚠️ Paiement hors plateforme détecté dans cette conversation</p>
            <p class="mt-0.5">Ne validez jamais un lien de paiement reçu par SMS. Privilégiez le <span class="font-semibold">paiement sécurisé Swap'Îles</span> ou les <span class="font-semibold">espèces en main propre</span>.</p>
        </div>
    @endif

    @if(session('moderation_payment_warning'))
        <div class="shrink-0 border-t border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-bold">⚠️ {{ \App\Support\MessageModeration::PAYMENT_WARNING_TITLE }}</p>
            @foreach(\App\Support\MessageModeration::paymentWarningLines() as $line)
                <p class="mt-1">{{ $line }}</p>
            @endforeach
            @if(session('moderation_had_attachment'))
                <p class="mt-2 font-semibold">📎 Ta photo/vidéo n'a pas été conservée — pense à la rajouter avant d'envoyer.</p>
            @endif
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" id="mod-edit" class="rounded-xl border border-amber-400 bg-white px-4 py-2 font-semibold text-amber-800 hover:bg-amber-100">Modifier mon message</button>
                <button type="button" id="mod-send-anyway" class="rounded-xl bg-amber-600 px-4 py-2 font-semibold text-white hover:bg-amber-700">Envoyer quand même</button>
            </div>
        </div>
    @endif

    {{-- Saisie, toujours en bas --}}
    @if($conversationBlocked)
        <div class="shrink-0 border-t border-gray-100 bg-gray-50 p-4 text-center text-sm text-gray-600" style="padding-bottom:max(16px, env(safe-area-inset-bottom))">
            @if($iBlockedThem)
                🚫 Tu as bloqué ce membre. Débloque-le (menu ⋯) pour reprendre la conversation.
            @else
                Vous ne pouvez plus échanger de messages avec ce membre.
            @endif
        </div>
    @else
        <form method="POST" id="compose-form"
              action="{{ $hasListing ? route('account.messages.store', ['listing' => $listing, 'user' => $user]) : route('account.messages.store.general', $user) }}"
              enctype="multipart/form-data"
              class="shrink-0 border-t border-gray-100 bg-white px-2 pt-2" style="padding-bottom:max(8px, env(safe-area-inset-bottom))">
            @csrf
            <input type="hidden" name="moderation_confirm" id="moderation_confirm" value="">
            <input type="hidden" name="reply_to_id" id="reply_to_id" value="{{ old('reply_to_id') }}">

            {{-- Réponse en cours (après avoir glissé un message) --}}
            <div data-reponse class="mx-1 mb-2 hidden items-start gap-2 rounded-2xl border-l-4 border-teal-500 bg-gray-50 px-3 py-2">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold text-teal-700">Réponse à <span data-reponse-auteur></span></p>
                    <p data-reponse-extrait class="truncate text-sm text-gray-600"></p>
                </div>
                <button type="button" data-reponse-annuler class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-gray-400 hover:bg-gray-200" aria-label="Annuler la réponse">✕</button>
            </div>

            <div id="attachment-preview" class="mx-1 mb-2 hidden items-center gap-2 rounded-xl bg-gray-50 px-3 py-2 text-sm text-gray-600">
                <span aria-hidden="true">📎</span>
                <span id="attachment-name" class="min-w-0 flex-1 truncate"></span>
                <button type="button" id="attachment-remove" class="font-semibold text-red-600">Retirer</button>
            </div>

            <div class="flex items-end gap-2">
                <label for="attachment" title="Ajouter une photo ou une vidéo (50 Mo max)" aria-label="Ajouter une photo ou une vidéo"
                       class="grid h-11 w-11 shrink-0 cursor-pointer place-items-center rounded-full bg-gray-100 text-xl text-gray-700 hover:bg-gray-200">📷</label>
                <input id="attachment" name="attachment" type="file" accept="image/*,video/*" class="hidden">

                <label for="body" class="sr-only">Message</label>
                <textarea id="body" name="body" rows="1" placeholder="Écrire un message…"
                          class="max-h-32 min-h-11 flex-1 resize-none rounded-3xl border-0 bg-gray-100 px-4 py-2.5 text-base leading-6 outline-none focus:ring-2 focus:ring-teal-100">{{ old('body') }}</textarea>

                <button data-envoyer class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-teal-600 text-white transition hover:bg-teal-700 disabled:bg-gray-200 disabled:text-gray-400" aria-label="Envoyer">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true"><path d="M3.4 20.4 21 12 3.4 3.6l-.01 6.53L15 12 3.39 13.87z"/></svg>
                </button>
            </div>
            @error('body')<p class="mx-2 mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            @error('attachment')<p class="mx-2 mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </form>
    @endif
</section>

{{-- Lightbox : agrandir les photos au clic --}}
<div id="msg-lightbox" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/80 p-4" role="dialog" aria-modal="true">
    <button type="button" id="msg-lightbox-close" class="absolute right-4 top-4 grid h-11 w-11 place-items-center rounded-full bg-white/15 text-2xl text-white hover:bg-white/25" aria-label="Fermer">✕</button>
    <img id="msg-lightbox-img" src="" alt="" class="max-h-[90vh] max-w-[95vw] rounded-2xl object-contain shadow-2xl">
</div>

<script>
(function () {
    var ecran = document.querySelector('[data-conversation]');
    if (!ecran) return;
    var fil = ecran.querySelector('[data-fil]');
    var form = document.getElementById('compose-form');
    var body = document.getElementById('body');
    var input = document.getElementById('attachment');
    var envoyer = ecran.querySelector('[data-envoyer]');

    // Plein écran : rien ne défile derrière ; on arrive sur les derniers messages.
    document.documentElement.style.overflow = 'hidden';
    document.addEventListener('turbo:before-render', function () { document.documentElement.style.overflow = ''; }, { once: true });
    function enBas() { fil.scrollTop = fil.scrollHeight; }
    enBas();
    window.addEventListener('load', enBas);

    // Clavier du téléphone : l'écran se cale sur la partie visible.
    var vv = window.visualViewport;
    function ajuster() {
        if (!vv) return;
        ecran.style.height = vv.height + 'px';
        ecran.style.transform = 'translateY(' + vv.offsetTop + 'px)';
    }
    if (vv) { vv.addEventListener('resize', function () { ajuster(); enBas(); }); vv.addEventListener('scroll', ajuster); ajuster(); }

    if (!form) return;

    // Zone de saisie qui grandit avec le texte ; flèche active seulement s'il y a quelque chose.
    function majSaisie() {
        body.style.height = 'auto';
        body.style.height = Math.min(body.scrollHeight, 128) + 'px';
        envoyer.disabled = !body.value.trim() && !(input.files && input.files.length);
    }
    body.addEventListener('input', majSaisie);
    majSaisie();
    body.addEventListener('focus', function () { setTimeout(enBas, 250); });
    // Ordinateur : Entrée envoie, Maj+Entrée va à la ligne.
    body.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !('ontouchstart' in window)) {
            e.preventDefault();
            if (!envoyer.disabled) form.requestSubmit ? form.requestSubmit() : form.submit();
        }
    });
    form.addEventListener('submit', function () { envoyer.disabled = true; });

    // Pièce jointe
    var preview = document.getElementById('attachment-preview');
    var nameEl = document.getElementById('attachment-name');
    input.addEventListener('change', function () {
        if (input.files && input.files.length) {
            nameEl.textContent = input.files[0].name;
            preview.classList.remove('hidden'); preview.classList.add('flex');
        }
        majSaisie();
    });
    document.getElementById('attachment-remove').addEventListener('click', function () {
        input.value = '';
        preview.classList.add('hidden'); preview.classList.remove('flex');
        majSaisie();
    });

    // --- Répondre à un message (glisser vers la droite, ou ↩ sur ordinateur) ---
    var bandeau = ecran.querySelector('[data-reponse]');
    var champReponse = document.getElementById('reply_to_id');
    function repondreA(ligne) {
        champReponse.value = ligne.dataset.messageId;
        bandeau.querySelector('[data-reponse-auteur]').textContent = ligne.dataset.messageAuteur;
        bandeau.querySelector('[data-reponse-extrait]').textContent = ligne.dataset.messageExtrait || '';
        bandeau.classList.remove('hidden'); bandeau.classList.add('flex');
        body.focus();
        if (navigator.vibrate) navigator.vibrate(8);
    }
    bandeau.querySelector('[data-reponse-annuler]').addEventListener('click', function () {
        champReponse.value = '';
        bandeau.classList.add('hidden'); bandeau.classList.remove('flex');
    });
    if (champReponse.value) {
        var deja = fil.querySelector('[data-message-id="' + champReponse.value + '"]');
        if (deja) repondreA(deja);
    }
    fil.querySelectorAll('[data-repondre]').forEach(function (b) {
        b.addEventListener('click', function () { repondreA(b.closest('[data-message-id]')); });
    });

    fil.querySelectorAll('[data-message-id]').forEach(function (ligne) {
        var bulle = ligne.querySelector('[data-bulle]');
        var fleche = ligne.querySelector('[data-fleche-reponse]');
        var x0 = 0, y0 = 0, dx = 0, actif = false, decide = false;
        ligne.addEventListener('touchstart', function (e) {
            x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; dx = 0; actif = false; decide = false;
            bulle.style.transition = 'none';
        }, { passive: true });
        ligne.addEventListener('touchmove', function (e) {
            var mx = e.touches[0].clientX - x0, my = e.touches[0].clientY - y0;
            if (!decide && (Math.abs(mx) > 8 || Math.abs(my) > 8)) { decide = true; actif = mx > 0 && Math.abs(mx) > Math.abs(my); }
            if (!actif) return;
            dx = Math.max(0, Math.min(mx, 90));
            bulle.style.transform = 'translateX(' + dx + 'px)';
            fleche.style.opacity = Math.min(1, dx / 60);
        }, { passive: true });
        ligne.addEventListener('touchend', function () {
            bulle.style.transition = 'transform .2s';
            bulle.style.transform = '';
            fleche.style.opacity = 0;
            if (actif && dx > 60) repondreA(ligne);
            actif = false;
        });
    });

    // Toucher une citation : on remonte au message d'origine, mis en surbrillance.
    fil.querySelectorAll('[data-aller-a]').forEach(function (b) {
        b.addEventListener('click', function () {
            var cible = fil.querySelector('[data-message-id="' + b.dataset.allerA + '"] [data-bulle]');
            if (!cible) return;
            cible.scrollIntoView({ behavior: 'smooth', block: 'center' });
            cible.animate([{ opacity: .35 }, { opacity: 1 }], { duration: 700, iterations: 2 });
        });
    });

    // Modération : « Modifier » recentre le champ, « Envoyer quand même »
    // renvoie le formulaire avec la confirmation.
    var modEdit = document.getElementById('mod-edit');
    var modSend = document.getElementById('mod-send-anyway');
    modEdit && modEdit.addEventListener('click', function () { body.focus(); });
    modSend && modSend.addEventListener('click', function () {
        document.getElementById('moderation_confirm').value = '1';
        form.submit();
    });
})();

(function () {
    var box = document.getElementById('msg-lightbox');
    var img = document.getElementById('msg-lightbox-img');
    var closeBtn = document.getElementById('msg-lightbox-close');
    if (!box || !img) return;
    function open(src) { img.src = src; box.classList.remove('hidden'); box.classList.add('flex'); }
    function close() { box.classList.add('hidden'); box.classList.remove('flex'); img.src = ''; }
    document.addEventListener('click', function (e) {
        var zoomable = e.target.closest('.js-zoomable');
        if (zoomable && zoomable.tagName === 'IMG') { e.preventDefault(); open(zoomable.currentSrc || zoomable.src); }
    }, { signal: window.swpPage ? window.swpPage() : undefined });
    box.addEventListener('click', function (e) {
        if (e.target === box || e.target === closeBtn || closeBtn.contains(e.target)) close();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); }, { signal: window.swpPage ? window.swpPage() : undefined });
})();
</script>
@endsection
