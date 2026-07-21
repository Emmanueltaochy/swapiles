@extends('layouts.app')

@section('title', $user->name . ' — Profil vendeur Swap\'Îles')

@section('content')

<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 mb-8">

            <div class="flex flex-col sm:flex-row sm:items-center gap-5">

                <div class="w-24 h-24 rounded-full bg-teal-100 flex items-center justify-center overflow-hidden text-4xl font-extrabold text-teal-800">
                    @if($user->avatar)
                        <img src="{{ $user->avatar }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                    @else
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    @endif
                </div>

                <div class="flex-1">
                    <h1 class="text-3xl font-extrabold text-gray-900">
                        {{ $user->name }}
                    </h1>

                    <div class="mt-2 flex flex-wrap gap-2 text-sm text-gray-600">
                        @if($user->territoire)
                            <span class="bg-gray-100 px-3 py-1.5 rounded-full">
                                📍 {{ $user->territoire }}
                            </span>
                        @endif

                        <span class="bg-gray-100 px-3 py-1.5 rounded-full">
                            ⭐ {{ number_format((float) $user->rating, 1, ',', ' ') }}/5
                        </span>

                        <span class="bg-gray-100 px-3 py-1.5 rounded-full">
                            🛍️ {{ $user->transactions_count ?? 0 }} transactions
                        </span>

                        @if($user->is_pro)
                            <span class="bg-teal-100 text-teal-800 px-3 py-1.5 rounded-full font-bold">
                                Pro
                            </span>
                        @endif
                    </div>
                </div>

                <div>
                    <button class="bg-gray-200 text-gray-500 font-bold px-5 py-3 rounded-2xl cursor-not-allowed">
                        Contacter bientôt
                    </button>
                </div>

            </div>

        </div>

        <div class="mb-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-teal-700">Annonces du vendeur</p>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900">
                    {{ $listings->total() }} annonces en ligne
                </h2>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-5">
            @forelse($listings as $listing)
                <a href="{{ route('listings.show', $listing) }}" class="group bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-0.5 transition duration-300">
                    <div class="relative aspect-[3/4] bg-gray-100 overflow-hidden">
                        @if($listing->images->first())
                            <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300 text-5xl">📦</div>
                        @endif
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
                            @if($listing->etat){{ $listing->etat }}@endif
                            @if($listing->taille) · {{ strtoupper($listing->taille) }}@endif
                        </p>
                    </div>
                </a>
            @empty
                <div class="col-span-full bg-white border border-dashed border-gray-300 rounded-3xl p-10 text-center">
                    <div class="text-5xl mb-3">🌴</div>
                    <h3 class="text-xl font-bold text-gray-900">Aucune annonce en ligne</h3>
                </div>
            @endforelse
        </div>

        <div class="mt-10">
            {{ $listings->links() }}
        </div>

    </div>
</section>

@endsection
