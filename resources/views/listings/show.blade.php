@extends('layouts.app')

@section('title', $listing->title . ($listing->territoire ? ' — ' . $listing->territoire : '') . ' | Swap\'Îles')
@php
    $seoPrice = $listing->price > 0 ? number_format($listing->price, 0, ',', ' ') . ' €' : 'Gratuit';
    $seoDesc = trim(($listing->marque ? $listing->marque . ' — ' : '') . $seoPrice . '. ' . \Illuminate\Support\Str::limit(strip_tags($listing->description), 130));
@endphp
@section('meta_description', \Illuminate\Support\Str::limit($seoDesc, 155))
@section('og_type', 'product')
@if($listing->images->first())
    @section('og_image', \Illuminate\Support\Str::startsWith($listing->images->first()->url, 'http') ? $listing->images->first()->url : url($listing->images->first()->url))
@endif

@php
    // Disponibilité et état au format Schema.org pour les rich results Google
    $schemaAvailability = $listing->status === 'sold'
        ? 'https://schema.org/SoldOut'
        : 'https://schema.org/InStock';
    $schemaCondition = in_array($listing->etat, ['Neuf avec étiquette', 'Neuf sans étiquette'], true)
        ? 'https://schema.org/NewCondition'
        : 'https://schema.org/UsedCondition';

    $schemaImages = $listing->images
        ->map(fn ($img) => \Illuminate\Support\Str::startsWith($img->url, 'http') ? $img->url : url($img->url))
        ->values()->all();

    $productSchema = array_filter([
        '@type' => 'Product',
        'name' => $listing->title,
        'description' => \Illuminate\Support\Str::limit(strip_tags($listing->description), 500) ?: $listing->title,
        'image' => $schemaImages ?: [asset('images/logo.png')],
        'sku' => 'SWP-' . $listing->id,
        'category' => trim(collect([$listing->category_level1, $listing->category_level2, $listing->category_level3])->filter()->implode(' > ')) ?: null,
        'brand' => $listing->marque ? ['@type' => 'Brand', 'name' => $listing->marque] : null,
        'color' => is_array($listing->couleurs) ? implode(', ', $listing->couleurs) : ($listing->couleurs ?: null),
        'itemCondition' => $schemaCondition,
    ]);

    if ($listing->price > 0) {
        $productSchema['offers'] = array_filter([
            '@type' => 'Offer',
            'url' => route('listings.show', $listing),
            'priceCurrency' => $listing->currency ?: 'EUR',
            'price' => number_format((float) $listing->price, 2, '.', ''),
            'availability' => $schemaAvailability,
            'itemCondition' => $schemaCondition,
            'seller' => $listing->user ? ['@type' => 'Person', 'name' => $listing->user->name] : null,
        ]);
    }

    $breadcrumbItems = collect([
        ['name' => 'Accueil', 'item' => url('/')],
        $listing->category_level1 ? ['name' => $listing->category_level1, 'item' => route('search', ['q' => $listing->category_level1])] : null,
        ['name' => $listing->title, 'item' => route('listings.show', $listing)],
    ])->filter()->values();

    $breadcrumbSchema = [
        '@type' => 'BreadcrumbList',
        'itemListElement' => $breadcrumbItems->map(fn ($crumb, $i) => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $crumb['name'],
            'item' => $crumb['item'],
        ])->all(),
    ];
@endphp

@push('structured_data')
<script type="application/ld+json">
{!! json_encode(['@context' => 'https://schema.org'] + $productSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
<script type="application/ld+json">
{!! json_encode(['@context' => 'https://schema.org'] + $breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')

<script>
    if (window.SWP && window.SWP.loaded) {
        window.SWP.track('ViewContent', {
            content_name: @json($listing->title),
            content_ids: [@json($listing->id)],
            content_type: 'product',
            value: {{ (float) $listing->price }},
            currency: 'EUR'
        });
    }
</script>

@php
    $images = $listing->images ?? collect();
    $mainImage = $images->first();

    $protectionFee = ($listing->requires_online_payment ?? false)
        ? max(0.99, round(($listing->price * 0.05) + 0.70, 2))
        : 0;

    $protectedTotal = $listing->price + $protectionFee;

    $sellerOtherListings = $listing->user
        ? $listing->user->listings()
            ->with('images')
            ->where('status', 'published')
            ->where('id', '!=', $listing->id)
            ->latest()
            ->limit(4)
            ->get()
        : collect();

    $similarListings = \App\Models\Listing::query()
        ->with(['images', 'user'])
        ->where('status', 'published')
        ->where('id', '!=', $listing->id)
        ->where('territoire', $listing->territoire)
        ->where(function ($q) use ($listing) {
            $q->where('category_level3', $listing->category_level3)
              ->orWhere('category_level2', $listing->category_level2)
              ->orWhere('category_level1', $listing->category_level1);
        })
        ->orderByRaw(
            "CASE
                WHEN title LIKE ? THEN 0
                WHEN category_level3 = ? THEN 1
                WHEN category_level2 = ? THEN 2
                WHEN category_level1 = ? THEN 3
                ELSE 4
            END",
            ['%' . strtok($listing->title, ' ') . '%', $listing->category_level3, $listing->category_level2, $listing->category_level1]
        )
        ->latest()
        ->limit(8)
        ->get();
@endphp

{{-- Barre de navigation --}}
<section class="bg-white border-b border-gray-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-3">
        <a href="{{ url()->previous() }}" class="text-sm font-semibold text-gray-500 hover:text-teal-700">← Retour</a>
        @if($listing->territoire)
            <a href="{{ route('search', ['territoire' => $listing->territoire]) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">
                Annonces à {{ $listing->territoire }} →
            </a>
        @endif
    </div>
</section>

<section class="bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

        @if(session('status'))
            <div class="mb-6 rounded-2xl bg-teal-50 text-teal-800 p-4 text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">

            {{-- GALERIE (gauche desktop / 1er mobile) --}}
            <div class="order-1 lg:col-span-7 space-y-3">
                <div class="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
                    @if($mainImage)
                        <div class="flex items-center justify-center bg-white">
                            <img loading="eager" fetchpriority="high" decoding="async" id="main-listing-image"
                                 src="{{ $mainImage->url }}" alt="{{ $listing->title }}{{ $listing->marque ? ' — ' . $listing->marque : '' }}{{ $listing->territoire ? ' — ' . $listing->territoire : '' }}"
                                 class="w-full max-h-[720px] object-contain cursor-zoom-in" data-gallery-main>
                        </div>
                    @else
                        <div class="aspect-[4/5] flex items-center justify-center text-7xl text-gray-300" aria-hidden="true">📦</div>
                    @endif
                </div>

                @if($images->count() > 1)
                    <div class="flex gap-2.5 overflow-x-auto pb-1 no-scrollbar">
                        @foreach($images as $image)
                            <button type="button"
                                    class="listing-thumb shrink-0 w-20 h-20 sm:w-24 sm:h-24 overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:border-teal-500"
                                    data-src="{{ $image->url }}" aria-label="Voir la photo">
                                <img loading="lazy" decoding="async" src="{{ $image->url }}" alt="{{ $listing->title }}" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ENCADRÉ D'ACHAT (droite desktop sticky / 2e mobile) --}}
            <aside class="order-2 lg:col-span-5 lg:row-span-2">
                <div class="lg:sticky lg:top-24 space-y-4">

                    <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm">
                        @if($listing->status === 'sold')
                            <span class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-sm font-semibold text-red-700">🔴 Vendu</span>
                        @endif

                        <div class="flex items-start justify-between gap-3">
                            <h1 class="text-2xl font-bold leading-tight text-gray-900">{{ $listing->title }}</h1>

                            @auth
                                <button type="button" id="favorite-toggle"
                                        data-url="{{ route('account.favorites.toggle', $listing) }}"
                                        data-favorited="{{ auth()->user()->favorites()->where('listing_id', $listing->id)->exists() ? '1' : '0' }}"
                                        aria-label="Ajouter aux favoris"
                                        class="grid h-11 w-11 shrink-0 place-items-center rounded-full border border-gray-200 text-xl shadow-sm transition hover:bg-gray-50 active:scale-90">
                                    {{ auth()->user()->favorites()->where('listing_id', $listing->id)->exists() ? '❤️' : '🤍' }}
                                </button>
                            @else
                                <a href="{{ route('login') }}" aria-label="Ajouter aux favoris"
                                   class="grid h-11 w-11 shrink-0 place-items-center rounded-full border border-gray-200 text-xl shadow-sm hover:bg-gray-50">🤍</a>
                            @endauth
                        </div>

                        {{-- Méta (allégé) --}}
                        <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium">
                            @if($listing->territoire)
                                <span class="rounded-full bg-teal-50 px-2.5 py-1 text-teal-700">📍 {{ $listing->territoire }}</span>
                            @endif
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-600">
                                👀 {{ number_format((int) $listing->views_count, 0, ',', ' ') }} vue{{ (int) $listing->views_count > 1 ? 's' : '' }}
                            </span>
                            <span id="favorite-count-badge" class="rounded-full bg-rose-50 px-2.5 py-1 text-rose-600">
                                ❤️ <span id="favorite-count">{{ number_format((int) ($listing->favorited_by_count ?? 0), 0, ',', ' ') }}</span> favori<span id="favorite-plural">{{ (int) ($listing->favorited_by_count ?? 0) > 1 ? 's' : '' }}</span>
                            </span>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-500">📅 {{ $listing->created_at->diffForHumans() }}</span>
                        </div>

                        {{-- Prix --}}
                        <div class="mt-5 rounded-xl bg-gray-50 border border-gray-100 p-4">
                            <p class="text-sm font-medium text-gray-500">Prix</p>
                            <p class="mt-0.5 text-3xl font-bold text-gray-900">
                                {{ $listing->price > 0 ? number_format($listing->price, 0, ',', ' ') . ' €' : 'Gratuit' }}
                            </p>

                            @if($listing->requires_online_payment ?? false)
                                <button type="button" class="prix-protege mt-3 block text-left"
                                        data-title="{{ e($listing->title) }}"
                                        data-price="{{ number_format($listing->price, 2, ',', ' ') }}"
                                        data-fee="{{ number_format($protectionFee, 2, ',', ' ') }}"
                                        data-total="{{ number_format($protectedTotal, 2, ',', ' ') }}">
                                    <span class="block text-base font-bold text-teal-700">{{ number_format($protectedTotal, 2, ',', ' ') }} € protégé 🛡️</span>
                                    <span class="mt-0.5 block text-xs text-gray-500">Protection acheteur incluse. Livraison calculée au paiement.</span>
                                </button>
                            @endif
                        </div>

                        {{-- Caractéristiques --}}
                        @if($listing->marque || $listing->taille || $listing->etat || $listing->category_level3)
                            <div class="mt-4 grid grid-cols-2 gap-2.5 text-sm">
                                @if($listing->marque)
                                    <div class="rounded-xl border border-gray-100 p-3"><p class="text-gray-500">Marque</p><p class="font-semibold text-gray-900">{{ $listing->marque }}</p></div>
                                @endif
                                @if($listing->taille)
                                    <div class="rounded-xl border border-gray-100 p-3"><p class="text-gray-500">Taille</p><p class="font-semibold text-gray-900">{{ strtoupper($listing->taille) }}</p></div>
                                @endif
                                @if($listing->etat)
                                    <div class="rounded-xl border border-gray-100 p-3"><p class="text-gray-500">État</p><p class="font-semibold text-gray-900">{{ $listing->etat }}</p></div>
                                @endif
                                @if($listing->category_level3)
                                    <div class="rounded-xl border border-gray-100 p-3"><p class="text-gray-500">Catégorie</p><p class="font-semibold text-gray-900">{{ str_replace('-', ' ', $listing->category_level3) }}</p></div>
                                @endif
                            </div>
                        @endif

                        {{-- Modes de livraison --}}
                        <div class="mt-4 flex flex-wrap gap-2 text-xs font-medium">
                            @if(($listing->allows_colissimo ?? false) || ($listing->shipping_enabled ?? false))
                                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-blue-700">📦 Colissimo</span>
                            @endif
                            @if($listing->requires_online_payment ?? false)
                                <span class="rounded-full bg-teal-50 px-2.5 py-1 text-teal-700">🔒 CB sécurisé</span>
                            @endif
                            @if($listing->pickup_enabled ?? true)
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700">🤝 Main propre</span>
                            @endif
                        </div>

                        {{-- Actions --}}
                        @php
                            $isSold = $listing->status === 'sold';
                            $canBuyOnline = in_array($listing->listing_type, ['achat', 'negoce-prix'], true) && $listing->requires_online_payment && $listing->price > 0;
                            $canCash = in_array($listing->listing_type, ['achat', 'negoce-prix'], true) && $listing->allows_hand_delivery && $listing->price > 0;
                            $canOffer = ($listing->allows_offers || $listing->listing_type === 'negoce-prix') && $listing->price > 0;
                            $canExchange = $listing->allows_exchange || $listing->listing_type === 'echange-produits';
                            $canDon = $listing->listing_type === 'don' || $listing->price <= 0;

                            // Acheteur d'une autre île + vendeur sans Colissimo -> demande d'intérêt.
                            $isShippable = $listing->requires_online_payment && $listing->allows_colissimo;
                            $isCrossIsland = auth()->check() && auth()->id() !== $listing->user_id
                                && auth()->user()->territoire && auth()->user()->territoire !== $listing->territoire;
                            $showInterest = $isCrossIsland && ! $isShippable && ! $isSold;
                            $alreadyInterested = $showInterest && \App\Models\ListingInterest::where('listing_id', $listing->id)
                                ->where('buyer_id', auth()->id())->exists();
                        @endphp
                        <div class="mt-6 space-y-3">
                            @if(!$isSold)
                                @auth
                                    @if(auth()->id() !== $listing->user_id)
                                        @if($showInterest)
                                            <div class="rounded-xl border-2 border-amber-300 bg-amber-50 p-4">
                                                <p class="text-sm font-bold text-amber-900">🌍 Ce vendeur est sur une autre île</p>
                                                <p class="mt-1 text-sm text-amber-800">
                                                    Il n'a pas activé la livraison (Colissimo). Comme vous êtes sur une autre île, la remise en main propre est impossible.
                                                    En demandant la livraison, un message est envoyé au vendeur (et un e-mail) pour qu'il active Colissimo — vous serez prévenu dès que ce sera possible.
                                                </p>
                                                @if($alreadyInterested)
                                                    <p class="mt-3 rounded-lg bg-amber-100 px-3 py-2 text-sm font-semibold text-amber-800">✅ Demande de livraison envoyée ! Le produit est dans vos favoris — vous serez notifié dès qu'il sera livrable.</p>
                                                @else
                                                    <form method="POST" action="{{ route('listings.interest', $listing) }}" class="mt-3">
                                                        @csrf
                                                        <button class="w-full rounded-xl bg-amber-500 px-6 py-3.5 font-semibold text-white shadow-sm transition hover:bg-amber-600">
                                                            📩 Demander la livraison
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        @else
                                        @if($canBuyOnline)
                                            <a href="{{ route('checkout.show', $listing) }}" class="block w-full rounded-xl bg-teal-600 px-6 py-4 text-center font-semibold text-white shadow-sm transition hover:bg-teal-700 focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2">
                                                💳 Acheter par CB sécurisé
                                            </a>
                                        @endif

                                        @if($canCash)
                                            <form method="POST" action="{{ route('listings.request-mode', ['listing' => $listing, 'mode' => 'cash']) }}">
                                                @csrf
                                                <button class="w-full rounded-xl border-2 border-gray-900 px-6 py-3.5 font-semibold text-gray-900 transition hover:bg-gray-50">💵 Payer en espèces</button>
                                            </form>
                                        @endif

                                        @if($canExchange)
                                            <a href="{{ route('exchange.create', $listing) }}" class="block w-full rounded-xl border-2 border-indigo-500 px-6 py-3.5 text-center font-semibold text-indigo-600 transition hover:bg-indigo-50">🔄 Proposer un échange</a>
                                        @endif

                                        @if($canDon)
                                            <form method="POST" action="{{ route('listings.request-mode', ['listing' => $listing, 'mode' => 'don']) }}">
                                                @csrf
                                                <button class="w-full rounded-xl border-2 border-emerald-500 px-6 py-3.5 font-semibold text-emerald-600 transition hover:bg-emerald-50">🎁 Demander ce don</button>
                                            </form>
                                        @endif

                                        {{-- Faire une offre --}}
                                        @if($canOffer)
                                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                                                <p class="mb-3 font-semibold text-gray-900">Faire une offre</p>
                                                <form method="POST" action="{{ route('offers.store', $listing) }}" class="space-y-3">
                                                    @csrf
                                                    <div class="relative">
                                                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg font-bold text-teal-700">€</span>
                                                        <label for="offer_amount" class="sr-only">Montant de votre offre</label>
                                                        <input id="offer_amount" name="amount" type="number" step="0.01" min="0" inputmode="decimal" placeholder="Ex. 25" required
                                                               class="h-13 w-full rounded-xl border-2 border-gray-200 bg-white py-3.5 pl-11 pr-4 text-lg font-semibold text-gray-900 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-teal-600 focus:ring-4 focus:ring-teal-100">
                                                    </div>
                                                    <label for="offer_message" class="sr-only">Message au vendeur</label>
                                                    <textarea id="offer_message" name="message" rows="3" placeholder="Ajoutez un petit message au vendeur…"
                                                              class="w-full resize-none rounded-xl border-2 border-gray-200 bg-white px-4 py-3 text-base text-gray-900 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-teal-600 focus:ring-4 focus:ring-teal-100"></textarea>
                                                    <button type="submit" class="w-full rounded-xl bg-gray-900 py-3.5 text-base font-semibold text-white shadow-sm transition hover:bg-black">
                                                        Envoyer mon offre
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                        @endif {{-- fin @if($showInterest) --}}
                                    @endif
                                @else
                                    <a href="{{ route('login') }}" class="block w-full rounded-xl bg-teal-600 px-6 py-4 text-center font-semibold text-white shadow-sm transition hover:bg-teal-700">
                                        Se connecter pour acheter
                                    </a>
                                @endauth
                            @endif

                            @auth
                                @if(auth()->id() !== $listing->user_id)
                                    <a href="{{ route('account.messages.start', $listing) }}" class="block w-full rounded-xl border-2 border-teal-600 px-6 py-3.5 text-center font-semibold text-teal-700 transition hover:bg-teal-50">
                                        💬 Envoyer un message
                                    </a>
                                @else
                                    <a href="{{ route('account.listings.edit', $listing) }}" class="block w-full rounded-xl border-2 border-gray-300 px-6 py-3.5 text-center font-semibold text-gray-700 transition hover:bg-gray-50">
                                        Modifier mon annonce
                                    </a>

                                    <div class="grid grid-cols-1 gap-2">
                                        @if($listing->price > 0)
                                            <form method="POST" action="{{ route('account.listings.cash-paid', $listing) }}">
                                                @csrf @method('PATCH')
                                                <button class="w-full rounded-xl bg-gray-900 px-5 py-3 font-semibold text-white hover:bg-black">💵 Paiement espèces reçu</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('account.listings.exchanged', $listing) }}">
                                            @csrf @method('PATCH')
                                            <button class="w-full rounded-xl bg-indigo-600 px-5 py-3 font-semibold text-white hover:bg-indigo-700">🔄 Échange effectué</button>
                                        </form>
                                        <form method="POST" action="{{ route('account.listings.given', $listing) }}">
                                            @csrf @method('PATCH')
                                            <button class="w-full rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white hover:bg-emerald-700">🎁 Don remis</button>
                                        </form>
                                    </div>
                                @endif
                            @else
                                <a href="{{ route('login') }}" class="block w-full rounded-xl border-2 border-teal-600 px-6 py-3.5 text-center font-semibold text-teal-700 transition hover:bg-teal-50">
                                    Se connecter pour envoyer un message
                                </a>
                            @endauth
                        </div>
                    </div>

                    {{-- Partage sur les réseaux sociaux (promotion par la communauté) --}}
                    @php
                        $shareUrl = route('listings.show', $listing);
                        $shareText = 'Découvrez « ' . $listing->title . ' » sur Swap\'Îles 🌴';
                        $waHref = 'https://wa.me/?text=' . rawurlencode($shareText . ' ' . $shareUrl);
                        $fbHref = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($shareUrl);
                        $xHref = 'https://twitter.com/intent/tweet?text=' . rawurlencode($shareText) . '&url=' . rawurlencode($shareUrl);
                    @endphp
                    @php
                        $smsHref = 'sms:?&body=' . rawurlencode($shareText . ' ' . $shareUrl);
                    @endphp
                    <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p class="mb-3 text-sm font-semibold text-gray-900">📣 Partager cette annonce</p>

                        {{-- Bouton de partage natif (ouvre Instagram, Messages, etc. sur mobile) --}}
                        <button type="button"
                                data-share-url="{{ $shareUrl }}"
                                data-share-text="{{ $shareText }}"
                                onclick="swpShareListing(this)"
                                class="mb-3 flex w-full items-center justify-center gap-2 rounded-xl bg-teal-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-teal-700">
                            <span aria-hidden="true">📲</span> Partager
                        </button>

                        <div class="flex flex-wrap gap-2">
                            <a href="{{ $waHref }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1.5 rounded-xl bg-[#25D366] px-3.5 py-2 text-sm font-semibold text-white transition hover:opacity-90">
                                <span aria-hidden="true">🟢</span> WhatsApp
                            </a>
                            <button type="button"
                                    data-share-url="{{ $shareUrl }}"
                                    onclick="swpInstagram(this)"
                                    class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                    style="background:linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);">
                                <span aria-hidden="true">📸</span> Instagram
                            </button>
                            <a href="{{ $fbHref }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1.5 rounded-xl bg-[#1877F2] px-3.5 py-2 text-sm font-semibold text-white transition hover:opacity-90">
                                <span aria-hidden="true">📘</span> Facebook
                            </a>
                            <a href="{{ $smsHref }}"
                               class="inline-flex items-center gap-1.5 rounded-xl bg-[#34C759] px-3.5 py-2 text-sm font-semibold text-white transition hover:opacity-90">
                                <span aria-hidden="true">💬</span> Message
                            </a>
                            <a href="{{ $xHref }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1.5 rounded-xl bg-gray-900 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-black">
                                <span aria-hidden="true">✖️</span> X
                            </a>
                            <button type="button"
                                    data-share-url="{{ $shareUrl }}"
                                    data-share-text="{{ $shareText }}"
                                    onclick="swpShareListing(this)"
                                    class="js-share-copy inline-flex items-center gap-1.5 rounded-xl border-2 border-gray-200 px-3.5 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                                <span aria-hidden="true">🔗</span> Copier le lien
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-gray-400">Instagram : le lien est copié, collez-le dans votre story ou votre bio 📸</p>
                    </div>

                    {{-- Protection acheteur --}}
                    @if($canBuyOnline && !$isSold)
                        <div class="flex gap-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                            <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-teal-50 text-lg" aria-hidden="true">🛡️</div>
                            <div>
                                <h2 class="font-semibold text-gray-900">Protection acheteur</h2>
                                <p class="mt-0.5 text-sm text-gray-600">Pour les achats en ligne, Swap'Îles sécurise le paiement jusqu'à la bonne réception.</p>
                            </div>
                        </div>
                    @endif

                    {{-- Vendeur --}}
                    @if($listing->user)
                        <a href="{{ route('profiles.show', $listing->user) }}" class="block rounded-2xl border border-gray-100 bg-white p-4 shadow-sm transition hover:shadow-md">
                            <div class="flex items-center gap-3">
                                <div class="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-full bg-teal-100 text-lg font-bold text-teal-800">
                                    @if($listing->user->avatar)
                                        <img loading="lazy" decoding="async" src="{{ $listing->user->avatar }}" alt="{{ $listing->user->name }}" class="h-full w-full object-cover">
                                    @else
                                        {{ strtoupper(substr($listing->user->name, 0, 1)) }}
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-gray-900 truncate">{{ $listing->user->name }}</p>
                                    <p class="text-sm text-gray-500">⭐ {{ number_format((float) $listing->user->rating, 1, ',', ' ') }} · {{ $listing->user->transactions_count ?? 0 }} transactions</p>
                                </div>
                                <span class="shrink-0 text-sm font-semibold text-teal-700">Profil →</span>
                            </div>
                        </a>
                    @endif

                </div>
            </aside>

            {{-- DESCRIPTION (gauche desktop, sous la galerie / 3e mobile) --}}
            <div class="order-3 lg:col-span-7">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm">
                    <h2 class="font-semibold text-gray-900">Description</h2>
                    <p class="mt-3 whitespace-pre-line leading-relaxed text-gray-700">{{ $listing->description ?: 'Aucune description renseignée.' }}</p>

                    @if($listing->location_address || $listing->territoire)
                        <div class="mt-5 rounded-xl border border-gray-100 bg-gray-50 p-4 text-sm text-gray-700">📍 {{ $listing->location_address ?? $listing->territoire }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Dressing du membre (pleine largeur) --}}
        @if($sellerOtherListings->count())
            <section class="mt-8">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-gray-900">Dressing de {{ $listing->user->name }}</h2>
                    <a href="{{ route('profiles.show', $listing->user) }}" class="text-sm font-semibold text-teal-700">Voir le profil →</a>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    @foreach($sellerOtherListings as $other)
                        <a href="{{ route('listings.show', $other) }}" class="group">
                            <div class="aspect-[4/5] overflow-hidden rounded-2xl bg-gray-100">
                                @if($other->images->first())
                                    <img loading="lazy" decoding="async" src="{{ $other->images->first()->url }}" alt="{{ $other->title }}" class="h-full w-full object-cover transition group-hover:scale-[1.03]">
                                @else
                                    <div class="grid h-full w-full place-items-center text-4xl text-gray-300" aria-hidden="true">📦</div>
                                @endif
                            </div>
                            <p class="mt-2 line-clamp-1 text-sm font-medium text-gray-900">{{ $other->title }}</p>
                            <p class="mt-0.5 text-sm font-bold text-gray-900">{{ $other->price > 0 ? number_format($other->price, 0, ',', ' ') . ' €' : 'Gratuit' }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Annonces similaires (pleine largeur) --}}
        @if($similarListings->count())
            <section class="mt-10">
                <div class="mb-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-teal-600">Vous pourriez aimer</p>
                    <h2 class="text-xl md:text-2xl font-bold text-gray-900">Annonces similaires</h2>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-5">
                    @foreach($similarListings as $similar)
                        <a href="{{ route('listings.show', $similar) }}" class="group overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:shadow-md">
                            <div class="aspect-[4/5] overflow-hidden bg-gray-100">
                                @if($similar->images->first())
                                    <img loading="lazy" decoding="async" src="{{ $similar->images->first()->url }}" alt="{{ $similar->title }}" class="h-full w-full object-cover transition group-hover:scale-[1.03]">
                                @else
                                    <div class="grid h-full w-full place-items-center text-4xl text-gray-300" aria-hidden="true">📦</div>
                                @endif
                            </div>
                            <div class="p-3">
                                <p class="line-clamp-1 text-sm font-medium text-gray-900">{{ $similar->title }}</p>
                                <p class="mt-0.5 text-sm font-bold text-gray-900">{{ $similar->price > 0 ? number_format($similar->price, 0, ',', ' ') . ' €' : 'Gratuit' }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

    </div>
</section>

{{-- Galerie plein écran --}}
<div id="listing-gallery-modal" class="fixed inset-0 z-[9999] hidden bg-black/95 text-white">
    <button type="button" id="gallery-close" class="absolute top-4 right-4 z-20 grid h-12 w-12 place-items-center rounded-full bg-white/10 text-2xl font-bold" aria-label="Fermer">×</button>
    <button type="button" id="gallery-prev" class="absolute left-3 top-1/2 z-20 grid h-12 w-12 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-3xl" aria-label="Précédent">‹</button>
    <button type="button" id="gallery-next" class="absolute right-3 top-1/2 z-20 grid h-12 w-12 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-3xl" aria-label="Suivant">›</button>
    <div class="flex h-full w-full items-center justify-center px-4">
        <img loading="lazy" decoding="async" id="gallery-modal-image" src="" alt="" class="max-h-full max-w-full object-contain">
    </div>
    <div id="gallery-counter" class="absolute bottom-5 left-1/2 -translate-x-1/2 rounded-full bg-white/10 px-4 py-2 text-sm font-semibold"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mainImage = document.getElementById('main-listing-image');
    const thumbs = Array.from(document.querySelectorAll('.listing-thumb'));
    const galleryImages = thumbs.map(t => t.dataset.src).filter(Boolean);

    const modal = document.getElementById('listing-gallery-modal');
    const modalImage = document.getElementById('gallery-modal-image');
    const closeBtn = document.getElementById('gallery-close');
    const prevBtn = document.getElementById('gallery-prev');
    const nextBtn = document.getElementById('gallery-next');
    const counter = document.getElementById('gallery-counter');

    let currentIndex = 0;
    let touchStartX = 0;

    function setActive(index) {
        if (!mainImage || !galleryImages.length) return;

        currentIndex = (index + galleryImages.length) % galleryImages.length;
        mainImage.src = galleryImages[currentIndex];

        thumbs.forEach(t => t.classList.remove('border-teal-600', 'ring-2', 'ring-teal-100'));
        thumbs[currentIndex]?.classList.add('border-teal-600', 'ring-2', 'ring-teal-100');

        if (modalImage && !modal.classList.contains('hidden')) {
            modalImage.src = galleryImages[currentIndex];
            counter.textContent = (currentIndex + 1) + ' / ' + galleryImages.length;
        }
    }

    function openModal(index) {
        if (!modal || !modalImage || !galleryImages.length) return;
        setActive(index);
        modalImage.src = galleryImages[currentIndex];
        counter.textContent = (currentIndex + 1) + ' / ' + galleryImages.length;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        modal?.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    thumbs.forEach((thumb, index) => {
        thumb.addEventListener('click', function () {
            setActive(index);
            mainImage?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });

    let mainTouchStartX = 0;
    let mainTouchStartY = 0;
    let ignoreNextClick = false;

    mainImage?.addEventListener('touchstart', function (e) {
        mainTouchStartX = e.changedTouches[0].screenX;
        mainTouchStartY = e.changedTouches[0].screenY;
    }, { passive: true });

    mainImage?.addEventListener('touchend', function (e) {
        const touchEndX = e.changedTouches[0].screenX;
        const touchEndY = e.changedTouches[0].screenY;

        const diffX = mainTouchStartX - touchEndX;
        const diffY = mainTouchStartY - touchEndY;

        if (Math.abs(diffY) > 25) {
            ignoreNextClick = true;
            return;
        }

        if (Math.abs(diffX) > 45 && Math.abs(diffX) > Math.abs(diffY)) {
            ignoreNextClick = true;
            if (diffX > 0) {
                setActive(currentIndex + 1);
            } else {
                setActive(currentIndex - 1);
            }
            return;
        }
    }, { passive: true });

    mainImage?.addEventListener('click', function () {
        if (ignoreNextClick) {
            ignoreNextClick = false;
            return;
        }
        openModal(currentIndex);
    });
    closeBtn?.addEventListener('click', closeModal);
    prevBtn?.addEventListener('click', () => setActive(currentIndex - 1));
    nextBtn?.addEventListener('click', () => setActive(currentIndex + 1));

    modal?.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    modal?.addEventListener('touchstart', function (e) {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    modal?.addEventListener('touchend', function (e) {
        const touchEndX = e.changedTouches[0].screenX;
        const diff = touchStartX - touchEndX;
        if (Math.abs(diff) < 45) return;
        if (diff > 0) {
            setActive(currentIndex + 1);
        } else {
            setActive(currentIndex - 1);
        }
    }, { passive: true });

    document.addEventListener('keydown', function (e) {
        if (!modal || modal.classList.contains('hidden')) return;
        if (e.key === 'Escape') closeModal();
        if (e.key === 'ArrowLeft') setActive(currentIndex - 1);
        if (e.key === 'ArrowRight') setActive(currentIndex + 1);
    });

    setActive(0);
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('favorite-toggle');
    const countEl = document.getElementById('favorite-count');
    const pluralEl = document.getElementById('favorite-plural');

    if (!btn) return;

    btn.addEventListener('click', async function () {
        const url = btn.dataset.url;
        if (!url) return;

        btn.disabled = true;
        btn.classList.add('scale-110');

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({}),
            });

            const data = await response.json();

            btn.textContent = data.favorited ? '❤️' : '🤍';
            btn.dataset.favorited = data.favorited ? '1' : '0';

            if (countEl) countEl.textContent = new Intl.NumberFormat('fr-FR').format(data.count);
            if (pluralEl) pluralEl.textContent = data.count > 1 ? 's' : '';

            const pop = document.createElement('div');
            pop.textContent = data.favorited ? '❤️ Ajouté aux favoris' : 'Favori retiré';
            pop.className = 'fixed left-1/2 bottom-8 -translate-x-1/2 z-[9999] rounded-full bg-gray-950 text-white px-5 py-3 text-sm font-semibold shadow-2xl';
            document.body.appendChild(pop);

            btn.animate([
                { transform: 'scale(1)' },
                { transform: 'scale(1.35)' },
                { transform: 'scale(1)' }
            ], { duration: 320, easing: 'ease-out' });

            pop.animate([
                { opacity: 0, transform: 'translate(-50%, 16px) scale(.95)' },
                { opacity: 1, transform: 'translate(-50%, 0) scale(1)' },
                { opacity: 1, transform: 'translate(-50%, 0) scale(1)' },
                { opacity: 0, transform: 'translate(-50%, -12px) scale(.98)' }
            ], { duration: 1500, easing: 'ease-out' });

            setTimeout(() => pop.remove(), 1500);
        } catch (e) {
            console.error(e);
        } finally {
            btn.disabled = false;
            btn.classList.remove('scale-110');
        }
    });
});
</script>

<script>
// Partage d'annonce : menu natif du téléphone si dispo, sinon copie du lien.
function swpShareListing(btn) {
    var url = btn.getAttribute('data-share-url');
    var text = btn.getAttribute('data-share-text') || '';

    if (navigator.share) {
        navigator.share({ title: "Swap'Îles", text: text, url: url }).catch(function () {});
        return;
    }

    var done = function () {
        var original = btn.innerHTML;
        btn.innerHTML = '<span aria-hidden="true">✅</span> Lien copié';
        setTimeout(function () { btn.innerHTML = original; }, 1800);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done).catch(function () { window.prompt('Copiez le lien :', url); });
    } else {
        window.prompt('Copiez le lien :', url);
    }
}

// Instagram n'a pas de partage web : on copie le lien et on ouvre l'app/site.
function swpInstagram(btn) {
    var url = btn.getAttribute('data-share-url');
    var open = function () { window.open('https://www.instagram.com/', '_blank'); };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function () {
            var original = btn.innerHTML;
            btn.innerHTML = '<span aria-hidden="true">✅</span> Lien copié !';
            setTimeout(function () { btn.innerHTML = original; open(); }, 1000);
        }).catch(open);
    } else {
        window.prompt('Copiez le lien pour Instagram :', url);
    }
}
</script>

@endsection
