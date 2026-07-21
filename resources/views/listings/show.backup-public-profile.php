@extends('layouts.app')

@section('title', $listing->title . ' — Swap\'Îles')
@section('description', Str::limit($listing->description, 160))

@section('content')

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-teal-700 mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>
        Retour aux annonces
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <div class="space-y-3">
            @if($listing->images->isNotEmpty())
                <div class="aspect-square bg-white rounded-2xl overflow-hidden border border-gray-200">
                    <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" class="w-full h-full object-cover">
                </div>
                @if($listing->images->count() > 1)
                    <div class="grid grid-cols-4 gap-2">
                        @foreach($listing->images->skip(1) as $img)
                            <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden border border-gray-200">
                                <img src="{{ $img->url }}" alt="" class="w-full h-full object-cover">
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="aspect-square bg-gray-100 rounded-2xl flex items-center justify-center text-gray-300 text-6xl">📦</div>
            @endif
        </div>

        <div>
            @if($listing->listing_type === 'achat')
                <span class="inline-block bg-teal-100 text-teal-800 text-xs font-semibold px-2.5 py-1 rounded-full mb-3">🔒 Annonce protégée — Paiement CB</span>
            @elseif($listing->listing_type === 'echange-produits')
                <span class="inline-block bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-1 rounded-full mb-3">🔄 Échange de produits</span>
            @elseif($listing->listing_type === 'don')
                <span class="inline-block bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-1 rounded-full mb-3">🎁 Don</span>
            @endif

            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3">{{ $listing->title }}</h1>

            <div class="mb-6">
                @if($listing->price > 0)
                    <span class="text-3xl font-bold text-teal-700">{{ $listing->price }} €</span>
                @else
                    <span class="text-3xl font-bold text-green-600">Gratuit</span>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3 mb-6 text-sm">
                @if($listing->etat)
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-500 mb-0.5">État</p>
                        <p class="font-medium">{{ $listing->etat }}</p>
                    </div>
                @endif
                @if($listing->marque)
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-500 mb-0.5">Marque</p>
                        <p class="font-medium">{{ $listing->marque }}</p>
                    </div>
                @endif
                @if($listing->taille)
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-500 mb-0.5">Taille</p>
                        <p class="font-medium">{{ strtoupper($listing->taille) }}</p>
                    </div>
                @endif
                @if($listing->category_level3)
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-500 mb-0.5">Catégorie</p>
                        <p class="font-medium">{{ $listing->category_level3 }}</p>
                    </div>
                @endif
            </div>

            <div class="mb-6">
                <h3 class="font-semibold text-gray-900 mb-2">Description</h3>
                <p class="text-gray-700 whitespace-pre-line">{{ $listing->description }}</p>
            </div>

            @if($listing->location_address)
                <p class="text-sm text-gray-600 mb-6 flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                    {{ $listing->location_address }}
                </p>
            @endif

            <button class="w-full bg-teal-700 hover:bg-teal-800 text-white font-semibold py-3 px-6 rounded-full transition">
                Contacter le vendeur
            </button>
            <p class="text-xs text-gray-500 text-center mt-2">
                Inscription bientôt disponible
            </p>
        </div>

    </div>

</div>

@endsection
