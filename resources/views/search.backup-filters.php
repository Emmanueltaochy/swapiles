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
                    placeholder="Rechercher un article"
                    class="flex-1 px-4 py-3 bg-gray-100 rounded-2xl border-0 text-sm focus:ring-2 focus:ring-teal-600"
                >

                <button class="bg-teal-700 hover:bg-teal-800 text-white font-bold px-6 py-3 rounded-2xl transition">
                    Rechercher
                </button>
            </div>

            <div class="flex gap-2 overflow-x-auto pb-1">

                <select name="category" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">Catégorie</option>
                    <option value="Femme" @selected(request('category') === 'Femme')>Femme</option>
                    <option value="Homme" @selected(request('category') === 'Homme')>Homme</option>
                    <option value="Enfant" @selected(request('category') === 'Enfant')>Enfant</option>
                    <option value="Accessoires" @selected(request('category') === 'Accessoires')>Accessoires</option>
                </select>

                <select name="listing_type" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">Type</option>
                    <option value="achat" @selected(request('listing_type') === 'achat')>🔒 Protégé</option>
                    <option value="don" @selected(request('listing_type') === 'don')>🎁 Don</option>
                    <option value="echange-produits" @selected(request('listing_type') === 'echange-produits')>🔄 Échange</option>
                </select>

                <select name="territoire" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">Territoire</option>
                    <option value="La Réunion" @selected(request('territoire') === 'La Réunion')>🇷🇪 La Réunion</option>
                    <option value="Martinique" @selected(request('territoire') === 'Martinique')>🇲🇶 Martinique</option>
                    <option value="Guadeloupe" @selected(request('territoire') === 'Guadeloupe')>🇬🇵 Guadeloupe</option>
                    <option value="Guyane" @selected(request('territoire') === 'Guyane')>🇬🇫 Guyane</option>
                    <option value="Mayotte" @selected(request('territoire') === 'Mayotte')>🇾🇹 Mayotte</option>
                </select>

                <input name="min_price" value="{{ request('min_price') }}" type="number" placeholder="Prix min" class="shrink-0 w-28 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                <input name="max_price" value="{{ request('max_price') }}" type="number" placeholder="Prix max" class="shrink-0 w-28 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">

            </div>

        </form>

    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Articles</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $listings->total() }} résultats</p>
        </div>

        @if(request()->query())
            <a href="{{ route('search') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">
                Réinitialiser
            </a>
        @endif
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

                    @if($listing->listing_type === 'don')
                        <span class="absolute top-2 left-2 bg-green-600 text-white text-[11px] font-bold px-2 py-1 rounded-full">🎁 Don</span>
                    @elseif($listing->listing_type === 'echange-produits')
                        <span class="absolute top-2 left-2 bg-blue-600 text-white text-[11px] font-bold px-2 py-1 rounded-full">🔄 Échange</span>
                    @elseif($listing->listing_type === 'achat')
                        <span class="absolute top-2 left-2 bg-teal-700 text-white text-[11px] font-bold px-2 py-1 rounded-full">🔒 Protégé</span>
                    @endif
                </div>

                <div class="pt-2">
                    <p class="text-sm text-gray-800 line-clamp-1">{{ $listing->title }}</p>

                    <p class="text-sm font-bold text-gray-900 mt-0.5">
                        @if($listing->price > 0)
                            {{ number_format($listing->price, 0, ',', ' ') }} €
                        @else
                            <span class="text-green-600">Gratuit</span>
                        @endif
                    </p>

                    <p class="text-xs text-gray-500 line-clamp-1">
                        @if($listing->marque){{ $listing->marque }} · @endif
                        @if($listing->etat){{ $listing->etat }}@endif
                        @if($listing->taille) · {{ strtoupper($listing->taille) }}@endif
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
