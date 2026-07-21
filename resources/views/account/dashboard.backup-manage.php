@extends('layouts.app')

@section('title', 'Mon compte — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900">Bonjour {{ $user->name }}</h1>
            <p class="text-gray-500 mt-2">Bienvenue dans votre espace Swap'Îles.</p>

            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('account.listings.create') }}" class="bg-teal-700 text-white font-bold px-5 py-3 rounded-2xl hover:bg-teal-800 transition">
                    Déposer une annonce
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="bg-gray-100 text-gray-800 font-bold px-5 py-3 rounded-2xl hover:bg-gray-200 transition">
                        Se déconnecter
                    </button>
                </form>
            </div>
        </div>

        <div class="flex items-center justify-between mb-5">
            <h2 class="text-2xl font-extrabold text-gray-900">Mes annonces</h2>
            <span class="text-sm text-gray-500">{{ $listings->total() }} annonces</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-5">
            @forelse($listings as $listing)
                <a href="{{ route('listings.show', $listing) }}" class="group bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl transition">
                    <div class="relative aspect-[3/4] bg-gray-100 overflow-hidden">
                        @if($listing->images->first())
                            <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300 text-5xl">📦</div>
                        @endif
                    </div>

                    <div class="p-3">
                        <p class="font-extrabold text-gray-900">
                            @if($listing->price > 0)
                                {{ number_format($listing->price, 0, ',', ' ') }} €
                            @else
                                <span class="text-green-600">Gratuit</span>
                            @endif
                        </p>
                        <p class="text-sm text-gray-700 line-clamp-1">{{ $listing->title }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $listing->status }}</p>
                    </div>
                </a>
            @empty
                <div class="col-span-full bg-white rounded-3xl border border-dashed border-gray-300 p-10 text-center">
                    <div class="text-5xl mb-3">🌴</div>
                    <h3 class="text-xl font-bold text-gray-900">Aucune annonce</h3>
                    <p class="text-gray-500 mt-2">Vos annonces apparaîtront ici.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-8">
            {{ $listings->links() }}
        </div>

    </div>
</section>
@endsection
