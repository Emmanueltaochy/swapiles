@extends('layouts.app')

@section('title', $listing->title . ' — Swap\'Îles')

@section('content')

@php
    $images = $listing->images ?? collect();
    $mainImage = $images->first();

    $protectionFee = ($listing->shipping_enabled ?? false)
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

<section class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between gap-3">
        <a href="{{ url()->previous() }}" class="text-sm font-bold text-gray-500 hover:text-teal-700">
            ← Retour
        </a>

        <a href="{{ route('search', ['territoire' => $listing->territoire]) }}" class="text-sm font-bold text-teal-700 hover:text-teal-900">
            Voir les annonces à {{ $listing->territoire }} →
        </a>
    </div>
</section>

<section class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

        @if(session('status'))
            <div class="mb-6 bg-teal-50 text-teal-800 rounded-2xl p-4 text-sm font-semibold">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <div class="lg:col-span-8 space-y-5 order-1 lg:order-1">

                <div class="bg-white rounded-[34px] border border-gray-100 shadow-sm overflow-hidden">
                    @if($mainImage)
                        <div class="bg-white flex items-center justify-center">
                            <img loading="lazy" decoding="async" id="main-listing-image"
                                 src="{{ $mainImage->url }}"
                                 alt="{{ $listing->title }}"
                                 class="w-full max-h-[760px] object-contain cursor-zoom-in" data-gallery-main>
                        </div>
                    @else
                        <div class="aspect-[4/5] flex items-center justify-center text-gray-300 text-7xl">📦</div>
                    @endif
                </div>

                @if($images->count() > 1)
                    <div class="flex gap-3 overflow-x-auto pb-2 no-scrollbar">
                        @foreach($images as $image)
                            <button type="button"
                                    class="listing-thumb shrink-0 w-24 h-24 sm:w-32 sm:h-32 bg-white rounded-3xl border border-gray-100 overflow-hidden flex items-center justify-center hover:border-teal-500 transition"
                                    data-src="{{ $image->url }}">
                                <img loading="lazy" decoding="async" src="{{ $image->url }}" alt="{{ $listing->title }}" class="w-full h-full object-contain">
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="bg-white rounded-[34px] border border-gray-100 shadow-sm p-5 sm:p-6 order-3">
                    <h2 class="text-xl font-black text-gray-950">Description</h2>
                    <p class="mt-3 text-gray-700 leading-relaxed whitespace-pre-line">
                        {{ $listing->description ?: 'Aucune description renseignée.' }}
                    </p>

                    @if($listing->location_address || $listing->territoire)
                        <div class="mt-5 rounded-3xl bg-gray-50 border border-gray-100 p-4 text-sm text-gray-700">
                            📍 {{ $listing->location_address ?? $listing->territoire }}
                        </div>
                    @endif
                </div>

            </div>

            <aside class="lg:col-span-4 order-2 lg:order-2">
                <div class="lg:sticky lg:top-24 space-y-4">

                    <div class="bg-white rounded-[34px] border border-gray-100 shadow-sm p-5 sm:p-6">
                        @if($listing->status === 'sold')
                            <div class="inline-flex items-center gap-2 mb-4 rounded-full bg-red-100 text-red-700 px-4 py-2 text-sm font-black">
                                🔴 VENDU
                            </div>
                        @endif

                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h1 class="text-2xl sm:text-3xl font-black text-gray-950 leading-tight">
                                    {{ $listing->title }}
                                </h1>

                                <div class="mt-3 flex flex-wrap gap-2 text-xs font-black">
                                    @if($listing->territoire)
                                        <span class="rounded-full bg-teal-50 text-teal-700 px-3 py-2">📍 {{ $listing->territoire }}</span>
                                    @endif

                                    <span class="rounded-full bg-gray-100 text-gray-700 px-3 py-2">
                                        👀 {{ number_format((int) $listing->views_count, 0, ',', ' ') }} vue{{ (int) $listing->views_count > 1 ? 's' : '' }}
                                    </span>

                                    <span id="favorite-count-badge" class="rounded-full bg-rose-50 text-rose-700 px-3 py-2">
                                        ❤️ <span id="favorite-count">{{ number_format((int) ($listing->favorited_by_count ?? 0), 0, ',', ' ') }}</span> favori<span id="favorite-plural">{{ (int) ($listing->favorited_by_count ?? 0) > 1 ? 's' : '' }}</span>
                                    </span>

                                    <span class="rounded-full bg-indigo-50 text-indigo-700 px-3 py-2">
                                        📅 Publié {{ $listing->created_at->diffForHumans() }}
                                    </span>

                                    @if($listing->updated_at && $listing->updated_at->gt($listing->created_at->copy()->addDay()))
                                        <span class="rounded-full bg-amber-50 text-amber-700 px-3 py-2">
                                            🔄 Mis à jour {{ $listing->updated_at->diffForHumans() }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @auth
                                <button type="button"
                                        id="favorite-toggle"
                                        data-url="{{ route('account.favorites.toggle', $listing) }}"
                                        data-favorited="{{ auth()->user()->favorites()->where('listing_id', $listing->id)->exists() ? '1' : '0' }}"
                                        class="w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center text-xl hover:bg-gray-50 shadow-sm transition active:scale-90">
                                    {{ auth()->user()->favorites()->where('listing_id', $listing->id)->exists() ? '❤️' : '🤍' }}
                                </button>
                            @else
                                <a href="{{ route('login') }}" class="w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center text-xl hover:bg-gray-50 shadow-sm">
                                    🤍
                                </a>
                            @endauth
                        </div>

                        <div class="mt-6 rounded-3xl bg-gray-50 border border-gray-100 p-5">
                            <p class="text-sm font-bold text-gray-500">Prix article</p>
                            <p class="mt-1 text-4xl font-black text-gray-950">
                                {{ $listing->price > 0 ? number_format($listing->price, 0, ',', ' ') . ' €' : 'Gratuit' }}
                            </p>

                            @if($listing->shipping_enabled ?? false)
                                <button type="button"
                                        class="prix-protege mt-3 text-left"
                                        data-title="{{ e($listing->title) }}"
                                        data-price="{{ number_format($listing->price, 2, ',', ' ') }}"
                                        data-fee="{{ number_format($protectionFee, 2, ',', ' ') }}"
                                        data-total="{{ number_format($protectedTotal, 2, ',', ' ') }}">
                                    <span class="block text-lg font-black text-teal-700">
                                        {{ number_format($protectedTotal, 2, ',', ' ') }} € protégé 🛡️
                                    </span>
                                    <span class="block text-xs text-gray-500 mt-1">
                                        Inclut la protection acheteur. Livraison calculée au paiement.
                                    </span>
                                </button>
                            @endif
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            @if($listing->marque)
                                <div class="rounded-2xl bg-white border border-gray-100 p-3">
                                    <p class="text-gray-500">Marque</p>
                                    <p class="font-black text-gray-950">{{ $listing->marque }}</p>
                                </div>
                            @endif

                            @if($listing->taille)
                                <div class="rounded-2xl bg-white border border-gray-100 p-3">
                                    <p class="text-gray-500">Taille</p>
                                    <p class="font-black text-gray-950">{{ strtoupper($listing->taille) }}</p>
                                </div>
                            @endif

                            @if($listing->etat)
                                <div class="rounded-2xl bg-white border border-gray-100 p-3">
                                    <p class="text-gray-500">État</p>
                                    <p class="font-black text-gray-950">{{ $listing->etat }}</p>
                                </div>
                            @endif

                            @if($listing->category_level3)
                                <div class="rounded-2xl bg-white border border-gray-100 p-3">
                                    <p class="text-gray-500">Catégorie</p>
                                    <p class="font-black text-gray-950">{{ str_replace('-', ' ', $listing->category_level3) }}</p>
                                </div>
                            @endif
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2">
                            @if($listing->shipping_enabled ?? false)
                                <span class="inline-flex rounded-full bg-blue-50 text-blue-700 text-xs font-black px-3 py-2">📦 Colissimo</span>
                                <span class="inline-flex rounded-full bg-teal-50 text-teal-700 text-xs font-black px-3 py-2">🔒 Paiement CB sécurisé</span>
                            @endif

                            @if($listing->pickup_enabled ?? true)
                                <span class="inline-flex rounded-full bg-emerald-50 text-emerald-700 text-xs font-black px-3 py-2">🤝 Main propre</span>
                            @endif
                        </div>

                        <div class="mt-6 space-y-3">
                            @if($listing->listing_type === 'achat' && $listing->status !== 'sold')
                                @auth
                                    @if(auth()->id() !== $listing->user_id)
                                        @if($listing->requires_online_payment)
                                            <a href="{{ route('checkout.show', $listing) }}" class="block text-center w-full bg-teal-700 hover:bg-teal-800 text-white font-black rounded-2xl px-6 py-4 transition shadow-lg">
                                                💳 Acheter par CB sécurisé
                                            </a>
                                        @endif

                                        @if($listing->allows_hand_delivery && $listing->price > 0)
                                            <form method="POST" action="{{ route('listings.request-mode', ['listing' => $listing, 'mode' => 'cash']) }}">
                                                @csrf
                                                <button class="w-full border-2 border-gray-900 text-gray-900 hover:bg-gray-50 font-black rounded-2xl px-6 py-4 transition">
                                                    💵 Payer en espèces
                                                </button>
                                            </form>
                                        @endif

                                        @if($listing->listing_type === 'echange-produits' || $listing->allows_hand_delivery)
                                            <form method="POST" action="{{ route('listings.request-mode', ['listing' => $listing, 'mode' => 'exchange']) }}">
                                                @csrf
                                                <button class="w-full border-2 border-indigo-600 text-indigo-700 hover:bg-indigo-50 font-black rounded-2xl px-6 py-4 transition">
                                                    🔄 Proposer un échange
                                                </button>
                                            </form>
                                        @endif

                                        @if($listing->listing_type === 'don' || $listing->price <= 0)
                                            <form method="POST" action="{{ route('listings.request-mode', ['listing' => $listing, 'mode' => 'don']) }}">
                                                @csrf
                                                <button class="w-full border-2 border-emerald-600 text-emerald-700 hover:bg-emerald-50 font-black rounded-2xl px-6 py-4 transition">
                                                    🎁 Demander ce don
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                @else
                                    <a href="{{ route('login') }}" class="block text-center w-full bg-teal-700 hover:bg-teal-800 text-white font-black rounded-2xl px-6 py-4 transition shadow-lg">
                                        Se connecter pour acheter
                                    </a>
                                @endauth

                                <div class="rounded-3xl border border-gray-100 bg-gray-50 p-4">
                                    <p class="font-black text-gray-950 mb-3">Faire une offre</p>

                                    <form method="POST" action="{{ route('offers.store', $listing) }}" class="space-y-3">
                                        @csrf

                                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-lg font-extrabold text-teal-700">€</span>
                            <input
                                id="offer_amount"
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0"
                                inputmode="decimal"
                                placeholder="Ex. 25"
                                class="w-full h-14 rounded-2xl border-2 border-gray-200 bg-white pl-11 pr-4 text-lg font-extrabold text-gray-900 placeholder:text-gray-400 shadow-sm outline-none transition focus:border-teal-600 focus:ring-4 focus:ring-teal-100"
                                required
                            >
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            Saisissez le montant que vous souhaitez proposer au vendeur.
                        </p>

                                        <textarea
                            id="offer_message"
                            name="message"
                            rows="4"
                            placeholder="Ajoutez un petit message au vendeur..."
                            class="w-full rounded-2xl border-2 border-gray-200 bg-white px-4 py-4 text-base text-gray-900 placeholder:text-gray-400 shadow-sm outline-none transition focus:border-teal-600 focus:ring-4 focus:ring-teal-100 resize-none"
                        ></textarea>

                                        <button
                            type="submit"
                            class="w-full mt-2 rounded-2xl bg-[#081433] hover:bg-[#0c1b45] text-white font-extrabold text-xl py-4 shadow-md transition"
                        >
                            Envoyer mon offre
                        </button>
                                    </form>
                                </div>
                            @endif

                            @auth
                                @if(auth()->id() !== $listing->user_id)
                                    <a href="{{ route('account.messages.start', $listing) }}" class="block text-center w-full border-2 border-teal-700 text-teal-700 hover:bg-teal-50 font-black rounded-2xl px-6 py-4 transition">
                                        💬 Envoyer un message
                                    </a>
                                @else
                                    <a href="{{ route('account.listings.edit', $listing) }}" class="block text-center w-full border-2 border-gray-300 text-gray-700 hover:bg-gray-50 font-black rounded-2xl px-6 py-4 transition">
                                        Modifier mon annonce
                                    </a>

                                    <div class="grid grid-cols-1 gap-2">
                                        @if($listing->price > 0)
                                            <form method="POST" action="{{ route('account.listings.cash-paid', $listing) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="w-full bg-gray-900 hover:bg-black text-white font-black rounded-2xl px-5 py-3">
                                                    💵 Paiement espèces reçu
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('account.listings.exchanged', $listing) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="w-full bg-indigo-700 hover:bg-indigo-800 text-white font-black rounded-2xl px-5 py-3">
                                                🔄 Échange effectué
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('account.listings.given', $listing) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-black rounded-2xl px-5 py-3">
                                                🎁 Don remis
                                            </button>
                                        </form>
                                    </div>

                                @endif
                            @else
                                <a href="{{ route('login') }}" class="block text-center w-full border-2 border-teal-700 text-teal-700 hover:bg-teal-50 font-black rounded-2xl px-6 py-4 transition">
                                    Se connecter pour envoyer un message
                                </a>
                            @endauth
                        </div>
                    </div>

                    @if($listing->listing_type === 'achat' && $listing->status !== 'sold')
                        <div class="bg-white border border-gray-100 rounded-[34px] p-5 shadow-sm">
                            <div class="flex gap-3">
                                <div class="w-11 h-11 rounded-2xl bg-teal-50 flex items-center justify-center text-xl">🛡️</div>
                                <div>
                                    <h3 class="font-black text-gray-950">Protection acheteur</h3>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Pour les achats en ligne, Swap'Îles sécurise le paiement jusqu’à la bonne réception.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($listing->user)
                        <a href="{{ route('profiles.show', $listing->user) }}" class="block bg-white border border-gray-100 rounded-[34px] p-5 shadow-sm hover:shadow-md transition">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 rounded-full bg-teal-100 flex items-center justify-center overflow-hidden font-black text-teal-800 text-xl">
                                    @if($listing->user->avatar)
                                        <img loading="lazy" decoding="async" src="{{ $listing->user->avatar }}" alt="{{ $listing->user->name }}" class="w-full h-full object-cover">
                                    @else
                                        {{ strtoupper(substr($listing->user->name, 0, 1)) }}
                                    @endif
                                </div>

                                <div class="flex-1">
                                    <p class="font-black text-gray-950">{{ $listing->user->name }}</p>
                                    <p class="text-sm text-gray-500">
                                        ⭐ {{ number_format((float) $listing->user->rating, 1, ',', ' ') }}
                                        · {{ $listing->user->transactions_count ?? 0 }} transactions
                                    </p>
                                    <p class="text-xs text-teal-700 font-black mt-1">Voir le profil →</p>
                                </div>
                            </div>
                        </a>
                    @endif

                @if($sellerOtherListings->count())
                    <div class="bg-white rounded-[34px] border border-gray-100 shadow-sm p-5 sm:p-6 order-5">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-black text-gray-950">Dressing du membre</h2>
                            <a href="{{ route('profiles.show', $listing->user) }}" class="text-sm font-black text-teal-700">
                                Voir le profil →
                            </a>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            @foreach($sellerOtherListings as $other)
                                <a href="{{ route('listings.show', $other) }}" class="group">
                                    <div class="aspect-[4/5] bg-gray-100 rounded-3xl overflow-hidden">
                                        @if($other->images->first())
                                            <img loading="lazy" decoding="async" src="{{ $other->images->first()->url }}" alt="{{ $other->title }}" class="w-full h-full object-cover group-hover:scale-[1.03] transition">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-gray-300 text-4xl">📦</div>
                                        @endif
                                    </div>
                                    <p class="text-sm font-bold text-gray-900 line-clamp-1 mt-2">{{ $other->title }}</p>
                                    <p class="text-sm font-black text-gray-950 mt-1">
                                        {{ $other->price > 0 ? number_format($other->price, 0, ',', ' ') . ' €' : 'Gratuit' }}
                                    </p>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif



                </div>
            </aside>
        </div>

        @if($similarListings->count())
            <section class="mt-10">
                <div class="flex items-end justify-between mb-5">
                    <div>
                        <p class="text-sm font-black uppercase tracking-wide text-teal-700">Vous pourriez aimer</p>
                        <h2 class="text-2xl md:text-3xl font-black text-gray-950">Annonces similaires</h2>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
                    @foreach($similarListings as $similar)
                        <a href="{{ route('listings.show', $similar) }}" class="group bg-white rounded-3xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-md transition">
                            <div class="aspect-[4/5] bg-gray-100 overflow-hidden">
                                @if($similar->images->first())
                                    <img loading="lazy" decoding="async" src="{{ $similar->images->first()->url }}" alt="{{ $similar->title }}" class="w-full h-full object-cover group-hover:scale-[1.03] transition">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-300 text-4xl">📦</div>
                                @endif
                            </div>

                            <div class="p-3">
                                <p class="text-sm font-bold text-gray-900 line-clamp-1">{{ $similar->title }}</p>
                                <p class="text-sm font-black text-gray-950 mt-1">
                                    {{ $similar->price > 0 ? number_format($similar->price, 0, ',', ' ') . ' €' : 'Gratuit' }}
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

    </div>
</section>


<div id="listing-gallery-modal" class="fixed inset-0 z-[9999] hidden bg-black/95 text-white">
    <button type="button" id="gallery-close" class="absolute top-4 right-4 z-20 w-12 h-12 rounded-full bg-white/10 text-2xl font-black">×</button>
    <button type="button" id="gallery-prev" class="absolute left-3 top-1/2 -translate-y-1/2 z-20 w-12 h-12 rounded-full bg-white/10 text-3xl">‹</button>
    <button type="button" id="gallery-next" class="absolute right-3 top-1/2 -translate-y-1/2 z-20 w-12 h-12 rounded-full bg-white/10 text-3xl">›</button>

    <div class="h-full w-full flex items-center justify-center px-4">
        <img loading="lazy" decoding="async" id="gallery-modal-image" src="" alt="" class="max-w-full max-h-full object-contain">
    </div>

    <div id="gallery-counter" class="absolute bottom-5 left-1/2 -translate-x-1/2 rounded-full bg-white/10 px-4 py-2 text-sm font-black"></div>
</div>

@endsection




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
            pop.className = 'fixed left-1/2 bottom-8 -translate-x-1/2 z-[9999] rounded-full bg-gray-950 text-white px-5 py-3 text-sm font-black shadow-2xl';
            document.body.appendChild(pop);

            btn.animate([
                { transform: 'scale(1)' },
                { transform: 'scale(1.35)' },
                { transform: 'scale(1)' }
            ], {
                duration: 320,
                easing: 'ease-out'
            });

            pop.animate([
                { opacity: 0, transform: 'translate(-50%, 16px) scale(.95)' },
                { opacity: 1, transform: 'translate(-50%, 0) scale(1)' },
                { opacity: 1, transform: 'translate(-50%, 0) scale(1)' },
                { opacity: 0, transform: 'translate(-50%, -12px) scale(.98)' }
            ], {
                duration: 1500,
                easing: 'ease-out'
            });

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
