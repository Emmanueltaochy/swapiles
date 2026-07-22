@extends('layouts.app')

@section('title', $categoryLabel . ' d\'occasion à ' . $territoireLabel . ' | Swap\'Îles')
@section('meta_description', $categoryLabel . ' d\'occasion à ' . $territoireLabel . ' : achetez, vendez et échangez sur Swap\'Îles. ' . number_format($totalCount, 0, ',', ' ') . ' annonces à prix mini, livraison Colissimo ou remise en main propre.')
@section('canonical', route('catalog.category', [$territoireSlug, $categorySlug]))

@php
    $breadcrumb = [
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $territoireLabel, 'item' => route('catalog.territoire', $territoireSlug)],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $categoryLabel, 'item' => route('catalog.category', [$territoireSlug, $categorySlug])],
        ],
    ];
    $itemList = [
        '@type' => 'ItemList',
        'name' => $categoryLabel . ' d\'occasion à ' . $territoireLabel,
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

    <nav aria-label="Fil d'ariane" class="mb-4 text-sm text-gray-500">
        <a href="{{ route('home') }}" class="hover:text-teal-700">Accueil</a>
        <span class="mx-1.5">/</span>
        <a href="{{ route('catalog.territoire', $territoireSlug) }}" class="hover:text-teal-700">{{ $territoireLabel }}</a>
        <span class="mx-1.5">/</span>
        <span class="font-semibold text-gray-700">{{ $categoryLabel }}</span>
    </nav>

    <header class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900">
            {{ $categoryLabel }} d'occasion à {{ $territoireLabel }}
        </h1>
        <p class="mt-2 text-gray-500 max-w-3xl">
            {{ number_format($totalCount, 0, ',', ' ') }} annonce{{ $totalCount > 1 ? 's' : '' }}
            « {{ $categoryLabel }} » de seconde main à {{ $territoireLabel }}. Trouvez la bonne affaire près de chez vous,
            avec paiement sécurisé et livraison Colissimo.
        </p>
    </header>

    {{-- Autres catégories du territoire --}}
    @if($categories->isNotEmpty())
        <div class="mb-8 flex flex-wrap gap-2">
            @foreach($categories as $cat)
                <a href="{{ route('catalog.category', [$territoireSlug, $cat['slug']]) }}"
                   class="inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-semibold transition {{ $cat['slug'] === $categorySlug ? 'border-teal-600 bg-teal-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-teal-500 hover:text-teal-700' }}">
                    {{ $cat['label'] }}
                    <span class="text-xs {{ $cat['slug'] === $categorySlug ? 'text-teal-100' : 'text-gray-400' }}">{{ $cat['count'] }}</span>
                </a>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-5">
        @forelse($listings as $listing)
            @include('partials.listing-card', ['listing' => $listing])
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
                <div class="text-5xl" aria-hidden="true">🔍</div>
                <h2 class="mt-3 text-lg font-bold text-gray-900">Aucune annonce dans cette catégorie</h2>
                <a href="{{ route('catalog.territoire', $territoireSlug) }}" class="mt-5 inline-flex rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">Voir tout {{ $territoireLabel }}</a>
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $listings->links() }}
    </div>

</section>
@endsection
