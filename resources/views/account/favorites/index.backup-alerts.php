@extends('layouts.app')

@section('title', 'Mes favoris — Swap\'Îles')

@section('content')

<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900">
                Mes favoris ❤️
            </h1>

            <p class="text-gray-500 mt-2">
                Retrouvez les annonces que vous avez sauvegardées.
            </p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-5">

            @forelse($favorites as $listing)

                <a href="{{ route('listings.show', $listing) }}" class="group block">

                    <div class="relative aspect-[3/4] bg-gray-100 rounded-2xl overflow-hidden">

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
                        Aucun favori
                    </h2>

                    <p class="text-gray-500 mt-2">
                        Ajoutez des annonces à vos favoris pour les retrouver ici.
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
