@extends('layouts.app')

@section('title', 'Swap\'Îles — La marketplace seconde main des îles')

@section('content')

<section class="relative overflow-hidden bg-gradient-to-br from-teal-900 via-teal-800 to-emerald-700">
    <div class="absolute inset-0 bg-black/20"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-20">
        <div class="max-w-3xl">
            <span class="inline-flex items-center rounded-full bg-white/15 px-4 py-2 text-sm font-semibold text-white backdrop-blur">
                🌴 Marketplace locale des îles
            </span>

            <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-tight">
                La seconde main, pensée pour les îles.
            </h1>

            <p class="mt-5 text-lg sm:text-xl text-white/90 max-w-2xl leading-relaxed">
                Vendez, achetez, échangez ou donnez près de chez vous.
                Déjà <span class="font-bold text-white">{{ number_format($listings->total(), 0, ',', ' ') }}</span> annonces actives.
            </p>

            <form method="GET" action="{{ route('search') }}" class="mt-8 bg-white rounded-3xl shadow-2xl p-3 max-w-5xl">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Que recherches-tu ?"
                        class="md:col-span-2 px-4 py-3 bg-gray-50 rounded-2xl border-0 text-sm focus:ring-2 focus:ring-teal-600">

                    <select name="category" class="px-4 py-3 bg-gray-50 rounded-2xl border-0 text-sm focus:ring-2 focus:ring-teal-600">
                        <option value="">Catégorie</option>
                        <option value="Femme">Femme</option>
                        <option value="Homme">Homme</option>
                        <option value="Enfant">Enfant</option>
                        <option value="Accessoires">Accessoires</option>
                    </select>

                    <button class="bg-teal-700 hover:bg-teal-800 text-white font-bold px-6 py-3 rounded-2xl transition">
                        Rechercher
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 mt-8 mb-6">
    <div class="overflow-hidden rounded-[28px] shadow-lg border border-gray-100 bg-white">
        <img src="{{ asset('images/IMG_1431.jpg') }}" alt="Livraison Colissimo Swap'Îles" class="w-full h-auto object-cover">
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-5">
        <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Découvrez nos catégories</h2>

        <div class="hidden lg:flex items-center gap-2">
            <button type="button" onclick="document.getElementById('category-carousel').scrollBy({left: -520, behavior: 'smooth'})" class="w-11 h-11 rounded-full bg-white border border-gray-200 shadow-sm font-bold text-xl hover:bg-gray-50">←</button>
            <button type="button" onclick="document.getElementById('category-carousel').scrollBy({left: 520, behavior: 'smooth'})" class="w-11 h-11 rounded-full bg-white border border-gray-200 shadow-sm font-bold text-xl hover:bg-gray-50">→</button>
        </div>
    </div>

    <div id="category-carousel" class="flex gap-4 overflow-x-auto pb-4 snap-x snap-mandatory category-scroll">
        <a href="/recherche?q=&category=Femme&listing_type=&territoire=&min_price=&max_price=" class="shrink-0 snap-start w-[72vw] sm:w-[45vw] lg:w-[38vw] max-w-[520px] rounded-[30px] overflow-hidden bg-gray-100 shadow-sm">
            <img src="/images/Cat-femme.PNG" class="w-full h-[420px] object-cover" alt="Femme">
        </a>

        <a href="/recherche?q=&category=Enfant&listing_type=&territoire=&min_price=&max_price=" class="shrink-0 snap-start w-[72vw] sm:w-[45vw] lg:w-[38vw] max-w-[520px] rounded-[30px] overflow-hidden bg-gray-100 shadow-sm">
            <img src="/images/Cat-enfant.PNG" class="w-full h-[420px] object-cover" alt="Enfant">
        </a>

        <a href="/recherche?q=&category=Homme&listing_type=&territoire=&min_price=&max_price=" class="shrink-0 snap-start w-[72vw] sm:w-[45vw] lg:w-[38vw] max-w-[520px] rounded-[30px] overflow-hidden bg-gray-100 shadow-sm">
            <img src="/images/Cat-homme.PNG" class="w-full h-[420px] object-cover" alt="Homme">
        </a>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-end justify-between mb-6">
        <div>
            <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Annonces récentes</h2>
            <p class="text-gray-500 mt-1">Les dernières pépites publiées sur Swap’Îles.</p>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
        @foreach($listings as $listing)
            <a href="{{ route('listings.show', $listing) }}" class="group bg-white rounded-3xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-md transition">
                <div class="aspect-[3/4] bg-gray-100 overflow-hidden">
                    @if($listing->images->first())
                        <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" class="w-full h-full object-cover group-hover:scale-[1.03] transition duration-300">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-300 text-4xl">📦</div>
                    @endif
                </div>

                <div class="p-3">
                    <p class="text-sm font-bold text-gray-900 line-clamp-1">{{ $listing->title }}</p>

                    @if($listing->user)
                        <p class="text-xs text-gray-500 mt-1 line-clamp-1">
                            Vendu par <span class="font-semibold text-gray-700">{{ $listing->user->name }}</span>
                        </p>
                    @endif

                    @if(($listing->price ?? 0) > 0)
                        @php
                            $protectionFee = max(1, round($listing->price * 0.10, 2));
                            $protectedTotal = $listing->price + $protectionFee;
                        @endphp

                        <p class="text-lg font-extrabold text-gray-900 mt-2">{{ number_format($listing->price, 0, ',', ' ') }} €</p>

                        <button type="button"
                            class="prix-protege text-xs font-extrabold text-teal-700 hover:underline"
                            data-title="{{ e($listing->title) }}"
                            data-price="{{ number_format($listing->price, 2, ',', ' ') }}"
                            data-fee="{{ number_format($protectionFee, 2, ',', ' ') }}"
                            data-total="{{ number_format($protectedTotal, 2, ',', ' ') }}">
                            {{ number_format($protectedTotal, 2, ',', ' ') }} € protégé 🛡️
                        </button>
                    @else
                        <p class="text-lg font-extrabold text-teal-700 mt-2">Gratuit</p>
                    @endif
                </div>
            </a>
        @endforeach
    </div>

    <div class="mt-10">
        {{ $listings->links() }}
    </div>
</section>

<style>
.category-scroll::-webkit-scrollbar { display: none; }
.category-scroll { -ms-overflow-style: none; scrollbar-width: none; }
</style>

@endsection
