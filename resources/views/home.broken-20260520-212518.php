@extends('layouts.app')

@section('title', 'Swap\'Îles — La marketplace seconde main des îles')

@section('content')





<section class="bg-gray-50 border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">

            <a href="/?category=Femme" class="bg-white rounded-2xl p-4 text-center shadow-sm border hover:border-teal-300 hover:shadow-md transition">
                <div class="text-2xl mb-1">👗</div>
                <div class="text-sm font-semibold">Femme</div>
            </a>

            <a href="/?category=Homme" class="bg-white rounded-2xl p-4 text-center shadow-sm border hover:border-teal-300 hover:shadow-md transition">
                <div class="text-2xl mb-1">👕</div>
                <div class="text-sm font-semibold">Homme</div>
            </a>

            <a href="/?category=Enfant" class="bg-white rounded-2xl p-4 text-center shadow-sm border hover:border-teal-300 hover:shadow-md transition">
                <div class="text-2xl mb-1">🧸</div>
                <div class="text-sm font-semibold">Enfant</div>
            </a>

            <a href="/?category=Accessoires" class="bg-white rounded-2xl p-4 text-center shadow-sm border hover:border-teal-300 hover:shadow-md transition">
                <div class="text-2xl mb-1">👜</div>
                <div class="text-sm font-semibold">Accessoires</div>
            </a>

            <a href="/?listing_type=don" class="bg-white rounded-2xl p-4 text-center shadow-sm border hover:border-teal-300 hover:shadow-md transition">
                <div class="text-2xl mb-1">🎁</div>
                <div class="text-sm font-semibold">Dons</div>
            </a>

            <a href="/?listing_type=echange-produits" class="bg-white rounded-2xl p-4 text-center shadow-sm border hover:border-teal-300 hover:shadow-md transition">
                <div class="text-2xl mb-1">🔄</div>
                <div class="text-sm font-semibold">Échanges</div>
            </a>

        </div>

    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">

    <div class="mb-7 flex items-end justify-between gap-4">

        <div>

            @if(request()->filled('max_price') && request('max_price') <= 15)
                <p class="text-sm font-semibold text-teal-700">
                    🔥 Bonnes affaires à moins de 15 €
                </p>
            @else
                <p class="text-sm font-semibold text-teal-700">
                    🌴 Dernières annonces publiées
                </p>
            @endif

            <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900">
                {{ $listings->total() }} annonces trouvées
            </h2>

        </div>

    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-5">

        @forelse($listings as $listing)

            <a href="{{ route('listings.show', $listing) }}"
               class="group bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-0.5 transition duration-300">

                <div class="relative aspect-[3/4] bg-gray-100 overflow-hidden">

                    @if($listing->images->first())

                        <img
                            src="{{ $listing->images->first()->url }}"
                            alt="{{ $listing->title }}"
                            loading="lazy"
                            class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                        >

                    @else

                        <div class="w-full h-full flex items-center justify-center text-gray-300 text-5xl">
                            📦
                        </div>

                    @endif

                    <div class="absolute top-2 left-2">

                        @if($listing->listing_type === 'echange-produits')

                            <span class="bg-blue-600 text-white text-[11px] font-bold px-2 py-1 rounded-full shadow">
                                🔄 Échange
                            </span>

                        @elseif($listing->listing_type === 'don')

                            <span class="bg-green-600 text-white text-[11px] font-bold px-2 py-1 rounded-full shadow">
                                🎁 Don
                            </span>

                        @elseif($listing->listing_type === 'achat')

                            <span class="bg-teal-700 text-white text-[11px] font-bold px-2 py-1 rounded-full shadow">
                                🔒 Protégé
                            </span>

                        @else

                            <span class="bg-gray-900 text-white text-[11px] font-bold px-2 py-1 rounded-full shadow">
                                💵 Espèce
                            </span>

                        @endif

                    </div>

                </div>

                <div class="p-3">

                    <p class="font-extrabold text-gray-900 text-lg">

                        @if($listing->price > 0)

                            {{ number_format($listing->price, 0, ',', ' ') }} €

                        @else

                            <span class="text-green-600">Gratuit</span>

                        @endif

                    </p>

                    <p class="text-sm text-gray-800 line-clamp-1 mt-1 font-medium">
                        {{ $listing->title }}
                    </p>

                    <p class="text-xs text-gray-500 mt-1 line-clamp-1">

                        @if($listing->etat)
                            {{ $listing->etat }}
                        @endif

                        @if($listing->taille)
                            · {{ strtoupper($listing->taille) }}
                        @endif

                    </p>

                </div>

            </a>

        @empty

            <div class="col-span-full bg-white border border-dashed border-gray-300 rounded-3xl p-10 text-center">

                <div class="text-5xl mb-3">🌴</div>

                <h3 class="text-xl font-bold text-gray-900">
                    Aucune annonce trouvée
                </h3>

                <p class="text-gray-500 mt-2">
                    Essayez une autre recherche ou un autre filtre.
                </p>

            </div>

        @endforelse

    </div>

    <div class="mt-10">
        {{ $listings->links() }}
    </div>

</section>


<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="flex items-center justify-between mb-5">
        <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">
            Découvrez nos catégories
        </h2>

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

<style>
.category-scroll::-webkit-scrollbar { display: none; }
.category-scroll { -ms-overflow-style: none; scrollbar-width: none; }
</style>




@endsection
