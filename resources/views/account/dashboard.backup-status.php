@extends('layouts.app')

@section('title', 'Mon compte — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        @if(session('status'))
            <div class="mb-6 bg-teal-50 text-teal-800 rounded-2xl p-4 text-sm font-semibold">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900">Bonjour {{ $user->name }}</h1>
            <p class="text-gray-500 mt-2">Bienvenue dans votre espace Swap'Îles.</p>

            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('account.listings.create') }}" class="bg-teal-700 text-white font-bold px-5 py-3 rounded-2xl hover:bg-teal-800 transition">
                    Déposer une annonce
                </a>

                <a href="{{ route('account.profile.edit') }}" class="bg-gray-100 text-gray-800 font-bold px-5 py-3 rounded-2xl hover:bg-gray-200 transition">
                    Modifier mon profil
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

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($listings as $listing)
                <div class="bg-white rounded-3xl overflow-hidden border border-gray-100 shadow-sm">
                    <a href="{{ route('listings.show', $listing) }}" class="block">
                        <div class="relative aspect-[4/3] bg-gray-100 overflow-hidden">
                            @if($listing->images->first())
                                <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-300 text-5xl">📦</div>
                            @endif

                            <span class="absolute top-3 left-3 text-xs font-bold px-3 py-1 rounded-full
                                @if($listing->status === 'published') bg-green-600 text-white
                                @elseif($listing->status === 'sold') bg-gray-900 text-white
                                @else bg-gray-200 text-gray-700 @endif">
                                {{ $listing->status === 'published' ? 'En ligne' : ($listing->status === 'sold' ? 'Vendue' : $listing->status) }}
                            </span>
                        </div>
                    </a>

                    <div class="p-4">
                        <p class="font-extrabold text-gray-900 text-lg">
                            @if($listing->price > 0)
                                {{ number_format($listing->price, 0, ',', ' ') }} €
                            @else
                                <span class="text-green-600">Gratuit</span>
                            @endif
                        </p>

                        <p class="text-sm text-gray-700 line-clamp-1 mt-1">{{ $listing->title }}</p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('account.listings.edit', $listing) }}" class="px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-sm font-bold">
                                Modifier
                            </a>

                            @if($listing->status === 'published')
                                <form method="POST" action="{{ route('account.listings.sold', $listing) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="px-3 py-2 rounded-xl bg-gray-900 text-white text-sm font-bold">
                                        Vendue
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('account.listings.publish', $listing) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="px-3 py-2 rounded-xl bg-teal-700 text-white text-sm font-bold">
                                        Remettre en ligne
                                    </button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('account.listings.destroy', $listing) }}" onsubmit="return confirm('Supprimer cette annonce ?');">
                                @csrf
                                @method('DELETE')
                                <button class="px-3 py-2 rounded-xl bg-red-50 text-red-700 hover:bg-red-100 text-sm font-bold">
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
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
