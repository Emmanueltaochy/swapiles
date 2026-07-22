@extends('layouts.app')

@section('title', 'Achat, vente et échange d\'occasion à ' . $territoireLabel . ' | Swap\'Îles')
@section('meta_description', 'Achetez, vendez et échangez des articles d\'occasion à ' . $territoireLabel . ' sur Swap\'Îles : mode, maison, high-tech, enfants et plus. ' . number_format($totalCount, 0, ',', ' ') . ' annonces près de chez vous, livraison Colissimo ou remise en main propre.')
@section('canonical', route('catalog.territoire', $territoireSlug))

@php
    $breadcrumb = [
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $territoireLabel, 'item' => route('catalog.territoire', $territoireSlug)],
        ],
    ];
    $itemList = [
        '@type' => 'ItemList',
        'name' => 'Annonces d\'occasion à ' . $territoireLabel,
        'numberOfItems' => $totalCount,
        'itemListElement' => $listings->map(fn ($l, $i) => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'url' => route('listings.show', $l),
            'name' => $l->title,
        ])->all(),
    ];
@endphp

@push('structured_data')
<script type="application/ld+json">
{!! json_encode(['@context' => 'https://schema.org'] + $breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
<script type="application/ld+json">
{!! json_encode(['@context' => 'https://schema.org'] + $itemList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    {{-- Fil d'ariane --}}
    <nav aria-label="Fil d'ariane" class="mb-4 text-sm text-gray-500">
        <a href="{{ route('home') }}" class="hover:text-teal-700">Accueil</a>
        <span class="mx-1.5">/</span>
        <span class="font-semibold text-gray-700">{{ $territoireLabel }}</span>
    </nav>

    <header class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900">
            {{ $territoireFlag }} Achat, vente &amp; échange d'occasion à {{ $territoireLabel }}
        </h1>
        <p class="mt-2 text-gray-500 max-w-3xl">
            Découvrez {{ number_format($totalCount, 0, ',', ' ') }} annonce{{ $totalCount > 1 ? 's' : '' }} de seconde main à {{ $territoireLabel }} :
            mode, maison, high-tech, articles pour enfants et bien plus. Paiement sécurisé, livraison Colissimo dans les îles
            ou remise en main propre entre membres.
        </p>
    </header>

    {{-- Catégories du territoire (maillage interne) --}}
    @if($categories->isNotEmpty())
        <div class="mb-8 flex flex-wrap gap-2">
            @foreach($categories as $cat)
                <a href="{{ route('catalog.category', [$territoireSlug, $cat['slug']]) }}"
                   class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:border-teal-500 hover:text-teal-700 transition">
                    {{ $cat['label'] }}
                    <span class="text-xs text-gray-400">{{ $cat['count'] }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Grille d'annonces --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-5">
        @forelse($listings as $listing)
            @include('partials.listing-card', ['listing' => $listing])
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
                <div class="text-5xl" aria-hidden="true">🏝️</div>
                <h2 class="mt-3 text-lg font-bold text-gray-900">Aucune annonce pour l'instant à {{ $territoireLabel }}</h2>
                <p class="mt-1 text-gray-500">Soyez le premier à publier ! Vos articles seront visibles par toute la communauté.</p>
                <a href="{{ route('home') }}" class="mt-5 inline-flex rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">Voir toutes les annonces</a>
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $listings->links() }}
    </div>

    {{-- Liens vers les autres territoires --}}
    <div class="mt-12 border-t border-gray-100 pt-8">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Explorer les autres îles</h2>
        <div class="flex flex-wrap gap-2">
            @foreach($allTerritoires as $slug => $label)
                @if($slug !== $territoireSlug)
                    <a href="{{ route('catalog.territoire', $slug) }}"
                       class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:border-teal-500 hover:text-teal-700 transition">
                        {{ $label }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>

</section>
@endsection
