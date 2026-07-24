@extends('layouts.app')

@section('title', 'Mes favoris — Swap\'Îles')

@section('content')

@php
    $favoriteAlerts = \App\Models\FavoriteAlert::with('listing.images')
        ->where('user_id', auth()->id())
        ->whereNull('read_at')
        ->latest()
        ->take(5)
        ->get();
@endphp

@if($favoriteAlerts->count())
    <div class="mb-6 space-y-3">
        @foreach($favoriteAlerts as $alert)
            <a href="{{ $alert->listing ? route('listings.show', $alert->listing) : route('home') }}"
               class="block bg-teal-50 border border-teal-100 rounded-3xl p-4">
                <p class="font-extrabold text-teal-900">🔥 Baisse de prix sur un favori</p>
                <p class="text-sm text-teal-800 mt-1">
                    {{ $alert->listing->title ?? 'Annonce indisponible' }} :
                    {{ number_format($alert->old_price, 0, ',', ' ') }} € → 
                    {{ number_format($alert->new_price, 0, ',', ' ') }} €
                </p>
            </a>
        @endforeach
    </div>
@endif


<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-6">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900">
                Mes favoris ❤️
            </h1>

            <p class="text-gray-500 mt-2">
                Retrouvez les annonces que vous avez sauvegardées et vos demandes de livraison.
            </p>
        </div>

        {{-- Onglets : tous / vrais favoris / demandes de livraison --}}
        @php
            $tabs = [
                'all' => ['label' => '❤️ Tous', 'count' => $countAll],
                'direct' => ['label' => '⭐ Mes favoris', 'count' => $countDirect],
                'livraison' => ['label' => '📩 Demandes de livraison', 'count' => $countLivraison],
            ];
        @endphp
        <div class="mb-6 flex flex-wrap gap-2">
            @foreach($tabs as $key => $tab)
                <a href="{{ route('account.favorites.index', ['filter' => $key]) }}"
                   class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-bold border transition
                          {{ ($filter ?? 'all') === $key ? 'bg-teal-600 text-white border-teal-600' : 'bg-white text-gray-700 border-gray-200 hover:border-teal-300' }}">
                    {{ $tab['label'] }}
                    <span class="rounded-full px-2 py-0.5 text-xs {{ ($filter ?? 'all') === $key ? 'bg-white/20' : 'bg-gray-100 text-gray-500' }}">{{ $tab['count'] }}</span>
                </a>
            @endforeach
        </div>

        @if(($filter ?? 'all') === 'livraison')
            <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                📩 Ces articles sont sur une autre île sans livraison. Vous avez demandé au vendeur d'activer Colissimo — vous serez prévenu(e) dès qu'ils deviennent achetables.
            </div>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-5">

            @forelse($favorites as $listing)

                @php $isLivraison = ($interestIds ?? collect())->contains($listing->id); @endphp
                <a href="{{ route('listings.show', $listing) }}" class="group block">

                    <div class="relative aspect-[4/5] bg-gray-100 rounded-2xl overflow-hidden">

                        @if($listing->images->first())
                            <img src="{{ $listing->images->first()->url }}"
                                 alt="{{ $listing->title }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300 text-5xl">
                                📦
                            </div>
                        @endif

                        <button
                            type="button"
                            onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('account.favorites.toggle.get', $listing) }}';"
                            class="absolute top-2 right-2 w-9 h-9 rounded-full bg-white shadow flex items-center justify-center text-red-500 text-lg z-20"
                        >
                            ❤️
                        </button>

                        @if($isLivraison)
                            <span class="absolute inset-x-2 bottom-2 rounded-lg bg-amber-500/95 px-2 py-1 text-center text-[11px] font-semibold text-white">
                                📩 Demande de livraison
                            </span>
                        @endif

                    </div>

                    <div class="pt-2">
                        <p class="text-sm font-semibold text-gray-900 line-clamp-1">
                            {{ $listing->title }}
                        </p>

                        <p class="text-xs text-gray-500 mt-1 line-clamp-1">
                            {{ $listing->user->name ?? 'Utilisateur' }}
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

                <div class="col-span-full bg-white border border-dashed border-gray-300 rounded-3xl p-12 text-center">
                    <div class="text-5xl mb-3">💔</div>

                    <h2 class="text-xl font-bold text-gray-900">
                        @if(($filter ?? 'all') === 'livraison')
                            Aucune demande de livraison
                        @elseif(($filter ?? 'all') === 'direct')
                            Aucun favori
                        @else
                            Aucun favori
                        @endif
                    </h2>

                    <p class="text-gray-500 mt-2">
                        @if(($filter ?? 'all') === 'livraison')
                            Sur un article d'une autre île sans livraison, cliquez « Demander la livraison ».
                        @else
                            Ajoutez des annonces à vos favoris pour les retrouver ici.
                        @endif
                    </p>
                </div>

            @endforelse

        </div>

        <div class="mt-10">
            {{ $favorites->links() }}
        </div>

    </div>
</section>

@endsection
