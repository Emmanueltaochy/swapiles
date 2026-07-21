@extends('layouts.app')

@section('title', 'Recherche — Swap\'Îles')

@section('content')

<section class="bg-white border-b border-gray-200 sticky top-0 z-30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">

        <form method="GET" action="{{ route('search') }}" class="space-y-4">

            <div class="flex flex-col sm:flex-row gap-3">
                <input
                    type="text"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Rechercher des articles"
                    class="flex-1 px-4 py-3 bg-gray-100 rounded-2xl border-0 text-sm focus:ring-2 focus:ring-teal-600"
                >

                <button class="bg-teal-700 hover:bg-teal-800 text-white font-bold px-6 py-3 rounded-2xl transition">
                    Rechercher
                </button>
            </div>

            <div class="flex gap-2 overflow-x-auto pb-1">

                <select name="category" onchange="this.form.submit()" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">Catégorie</option>
                    <option value="Femme" @selected(request('category') === 'Femme')>Femmes</option>
                    <option value="Homme" @selected(request('category') === 'Homme')>Hommes</option>
                    <option value="Enfant" @selected(request('category') === 'Enfant')>Enfants</option>
                    <option value="Accessoires" @selected(request('category') === 'Accessoires')>Accessoires</option>
                    <option value="Maison" @selected(request('category') === 'Maison')>Maison</option>
                    <option value="Electronique" @selected(request('category') === 'Electronique')>Électronique</option>
                </select>

                <select name="listing_type" onchange="this.form.submit()" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">Type</option>
                    <option value="achat" @selected(request('listing_type') === 'achat')>🔒 Achat protégé</option>
                    <option value="negoce-prix" @selected(request('listing_type') === 'negoce-prix')>💵 Prix négociable</option>
                    <option value="don" @selected(request('listing_type') === 'don')>🎁 Don</option>
                    <option value="echange-produits" @selected(request('listing_type') === 'echange-produits')>🔄 Échange</option>
                    <option value="location-vetements" @selected(request('listing_type') === 'location-vetements')>👗 Location</option>
                </select>

                <select name="territoire" onchange="this.form.submit()" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">Territoire</option>
                    <option value="La Réunion" @selected(request('territoire') === 'La Réunion')>🇷🇪 La Réunion</option>
                    <option value="Martinique" @selected(request('territoire') === 'Martinique')>🇲🇶 Martinique</option>
                    <option value="Guadeloupe" @selected(request('territoire') === 'Guadeloupe')>🇬🇵 Guadeloupe</option>
                    <option value="Guyane" @selected(request('territoire') === 'Guyane')>🇬🇫 Guyane</option>
                    <option value="Mayotte" @selected(request('territoire') === 'Mayotte')>🇾🇹 Mayotte</option>
                </select>

                <select name="etat" onchange="this.form.submit()" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">État</option>
                    <option value="Neuf avec étiquette" @selected(request('etat') === 'Neuf avec étiquette')>Neuf avec étiquette</option>
                    <option value="Neuf sans étiquette" @selected(request('etat') === 'Neuf sans étiquette')>Neuf sans étiquette</option>
                    <option value="Très bon état" @selected(request('etat') === 'Très bon état')>Très bon état</option>
                    <option value="Bon état" @selected(request('etat') === 'Bon état')>Bon état</option>
                    <option value="Satisfaisant" @selected(request('etat') === 'Satisfaisant')>Satisfaisant</option>
                </select>

                <input name="taille" value="{{ request('taille') }}" type="text" placeholder="Taille" class="shrink-0 w-24 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">

                <input name="min_price" value="{{ request('min_price') }}" type="number" placeholder="Min €" class="shrink-0 w-24 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">

                <input name="max_price" value="{{ request('max_price') }}" type="number" placeholder="Max €" class="shrink-0 w-24 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">

                <select name="sort" onchange="this.form.submit()" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">Plus récents</option>
                    <option value="price_asc" @selected(request('sort') === 'price_asc')>Prix croissant</option>
                    <option value="price_desc" @selected(request('sort') === 'price_desc')>Prix décroissant</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Plus anciens</option>
                </select>

            </div>

            @if(request()->query())
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    @foreach(request()->query() as $key => $value)
                        @if($value)
                            <span class="inline-flex items-center gap-2 bg-gray-100 text-gray-700 px-3 py-1.5 rounded-full">
                                {{ $value }}
                            </span>
                        @endif
                    @endforeach

                    <a href="{{ route('search') }}" class="font-bold text-teal-700 hover:text-teal-900">
                        Réinitialiser
                    </a>
                </div>
            @endif

        </form>

    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Articles</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $listings->total() }} résultats</p>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-5">

        @forelse($listings as $listing)

            <a href="{{ route('listings.show', $listing) }}" class="group block">

                <div class="relative aspect-[3/4] bg-gray-100 rounded-2xl overflow-hidden">
                    @if($listing->images->first())
                        <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-300 text-5xl">📦</div>
                    @endif

                    @auth
                        <button
                            type="button"
                            onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('account.favorites.toggle.get', $listing) }}';"
                            class="absolute top-2 right-2 w-9 h-9 rounded-full bg-white/90 flex items-center justify-center shadow text-lg z-20"
                        >
                            {{ auth()->user()->favorites()->where('listing_id', $listing->id)->exists() ? '❤️' : '🤍' }}
                        </button>
                    @else
                        <button
                            type="button"
                            onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('login') }}';"
                            class="absolute top-2 right-2 w-9 h-9 rounded-full bg-white/90 flex items-center justify-center shadow text-gray-500 z-20"
                        >
                            ♡
                        </button>
                    @endauth

                    @if($listing->listing_type === 'don')
                        <span class="absolute top-2 left-2 bg-green-600 text-white text-[11px] font-bold px-2 py-1 rounded-full">🎁 Don</span>
                    @elseif($listing->listing_type === 'echange-produits')
                        <span class="absolute top-2 left-2 bg-blue-600 text-white text-[11px] font-bold px-2 py-1 rounded-full">🔄 Échange</span>
                    @elseif($listing->listing_type === 'achat')
                        <span class="absolute top-2 left-2 bg-teal-700 text-white text-[11px] font-bold px-2 py-1 rounded-full">🔒 Protégé</span>
                    @endif
                </div>

                <div class="pt-2">
                    <p class="text-sm font-semibold text-gray-900 line-clamp-1">{{ $listing->title }}</p>

                    <p class="text-xs text-gray-500 mt-1 line-clamp-1">
                        @if($listing->taille){{ strtoupper($listing->taille) }}@endif
                        @if($listing->etat) · {{ $listing->etat }}@endif
                        @if($listing->marque) · {{ $listing->marque }}@endif
                    </p>

                    <p class="text-sm font-extrabold text-gray-900 mt-1">
                        @if($listing->price > 0)
                            {{ number_format($listing->price, 0, ',', ' ') }} €
                        @else
                            <span class="text-green-600">Gratuit</span>
                        @endif
                    </p>
                </div>

            </a>

        @empty

            <div class="col-span-full bg-white border border-dashed border-gray-300 rounded-3xl p-10 text-center">
                <div class="text-5xl mb-3">🔍</div>
                <h3 class="text-xl font-bold text-gray-900">Aucune annonce trouvée</h3>
                <p class="text-gray-500 mt-2">Essaie avec un autre mot-clé ou enlève certains filtres.</p>
            </div>

        @endforelse

    </div>

    <div class="mt-10">
        {{ $listings->links() }}
    </div>

</section>

@endsection
